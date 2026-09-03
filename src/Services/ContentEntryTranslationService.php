<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\ContentEntryTranslationRepository;
use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Repositories\ContentTypePermalinkPatternRepository;
use Giga\Cms\Repositories\LanguageRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 *
 * title/slug non sono proprietà dirette di content_entries: sono per-lingua
 * per costruzione (Modello multilingua) — vivono qui.
 */
class ContentEntryTranslationService
{
    private ContentEntryTranslationRepository $translationRepository;
    private ContentEntryRepository $entryRepository;
    private ContentTypeRepository $typeRepository;
    private ContentTypePermalinkPatternRepository $permalinkPatternRepository;
    private LanguageRepository $languageRepository;
    private ContentEntryService $entryService;
    private RedirectService $redirectService;

    public function __construct()
    {
        $this->translationRepository     = new ContentEntryTranslationRepository();
        $this->entryRepository           = new ContentEntryRepository();
        $this->typeRepository            = new ContentTypeRepository();
        $this->permalinkPatternRepository = new ContentTypePermalinkPatternRepository();
        $this->languageRepository        = new LanguageRepository();
        $this->entryService              = new ContentEntryService();
        $this->redirectService       = new RedirectService();
    }

    public function getByEntryAndLanguage(int $entryId, int $languageId): array
    {
        $translation = $this->translationRepository->findByEntryAndLanguage($entryId, $languageId);
        if (!$translation) {
            throw new \RuntimeException("Nessuna traduzione per entry id={$entryId} nella lingua id={$languageId}.");
        }
        return $translation;
    }

    /** Tutte le traduzioni esistenti dell'entry, una per lingua attiva. */
    public function getByEntry(int $entryId): array
    {
        return $this->translationRepository->findByEntry($entryId);
    }

    /**
     * Crea o aggiorna la traduzione di un'entry per una lingua. slug
     * opzionale: se assente, generato dal title. Collisioni risolte con
     * un suffisso incrementale deterministico, scoped a (content_type,
     * lingua) — Collision strategy. Se lo slug esisteva già ed è cambiato,
     * genera automaticamente un redirect dal vecchio permalink al nuovo
     * (URL, Slug, Redirect: "generati automaticamente al cambio slug").
     */
    public function save(int $entryId, int $languageId, array $data): int
    {
        $entry    = $this->entryService->getById($entryId);
        $language = $this->languageRepository->findById($languageId);
        if (!$language) {
            throw new \RuntimeException('Lingua non trovata.');
        }
        $contentType = $this->typeRepository->findById((int) $entry['content_type_id']);
        if (!$contentType) {
            throw new \RuntimeException('Content Type non trovato.');
        }

        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new \RuntimeException('Il title è obbligatorio.');
        }

        $existing = $this->translationRepository->findByEntryAndLanguage($entryId, $languageId);

        $requestedSlug = trim($data['slug'] ?? '') !== '' ? $data['slug'] : $title;
        $slug = $this->ensureUniqueSlug(
            (int) $contentType['id'],
            $languageId,
            $requestedSlug,
            $existing ? (int) $existing['id'] : 0
        );

        // SEO (nullable per design: assente qui = usa il fallback calcolato
        // da getSeoMeta(), non un valore vuoto forzato in colonna).
        $seoFields = array_intersect_key($data, array_flip([
            'meta_title', 'meta_description', 'canonical_url', 'og_title', 'og_description', 'og_media_id',
        ]));

        if ($existing) {
            $oldSlug = $existing['slug'];

            $this->translationRepository->update((int) $existing['id'], array_merge([
                'title' => $title,
                'slug'  => $slug,
            ], $seoFields));

            if ($slug !== $oldSlug) {
                $this->redirectService->recordAutomaticRedirect(
                    $this->buildPermalink($contentType, $language, $oldSlug),
                    $this->buildPermalink($contentType, $language, $slug)
                );
            }

            return (int) $existing['id'];
        }

        return $this->translationRepository->create(array_merge([
            'entry_id'        => $entryId,
            'content_type_id' => $contentType['id'],
            'language_id'     => $languageId,
            'title'           => $title,
            'slug'            => $slug,
        ], $seoFields));
    }

    /**
     * Vista SEO risolta per (entry, lingua): valori espliciti se
     * impostati, altrimenti i fallback calcolati (SEO, layer trasversale).
     * robots combina content_entries.indexable (asse index/noindex, già
     * esistente) e .follow (asse follow/nofollow, nuovo) in una singola
     * direttiva — le 3 combinazioni del documento più index,nofollow
     * (non escluso, solo non citato esplicitamente).
     */
    public function getSeoMeta(int $entryId, int $languageId): array
    {
        $translation = $this->getByEntryAndLanguage($entryId, $languageId);
        $entry       = $this->entryService->getById($entryId);
        $contentType = $this->typeRepository->findById((int) $entry['content_type_id']);
        $language    = $this->languageRepository->findById($languageId);

        $metaTitle       = $translation['meta_title'] ?: $translation['title'];
        $metaDescription = $translation['meta_description'];

        return [
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDescription,
            'canonical_url'    => $translation['canonical_url'] ?: $this->buildPermalink($contentType, $language, $translation['slug']),
            'robots'           => ($entry['indexable'] ? 'index' : 'noindex') . ',' . ($entry['follow'] ? 'follow' : 'nofollow'),
            'og_title'         => $translation['og_title'] ?: $metaTitle,
            'og_description'   => $translation['og_description'] ?: $metaDescription,
            'og_media_id'      => $translation['og_media_id'] !== null ? (int) $translation['og_media_id'] : null,
            'json_ld_type'     => $contentType['json_ld_type'],
        ];
    }

    public function getPermalink(int $entryId, int $languageId): string
    {
        $translation = $this->getByEntryAndLanguage($entryId, $languageId);
        $entry       = $this->entryService->getById($entryId);
        $contentType = $this->typeRepository->findById((int) $entry['content_type_id']);
        $language    = $this->languageRepository->findById($languageId);

        return $this->buildPermalink($contentType, $language, $translation['slug']);
    }

    /**
     * Pubblico effettivo IN UNA LINGUA: combina lo stato dell'entry
     * (ContentEntryService::isEffectivelyPublic(), language-agnostic) con
     * l'esistenza della traduzione — "una traduzione mancante non rende
     * automaticamente pubblica l'entry in quella lingua tramite fallback"
     * (Policy contenuto incompleto V1). Nessun fallback: se manca la
     * traduzione, false, punto — mai un'altra lingua al suo posto.
     */
    public function isPubliclyVisible(int $entryId, int $languageId): bool
    {
        $entry = $this->entryService->getById($entryId);
        if (!$this->entryService->isEffectivelyPublic($entry)) {
            return false;
        }

        return $this->translationRepository->findByEntryAndLanguage($entryId, $languageId) !== false;
    }

    public function delete(int $id): void
    {
        $translation = $this->translationRepository->findById($id);
        if (!$translation) {
            throw new \RuntimeException('Traduzione non trovata.');
        }
        $this->translationRepository->delete($id);
    }

    /**
     * Prefisso lingua sempre presente (anche per la lingua di default) —
     * semplificazione deliberata: il documento mostra l'esempio con
     * entrambe le lingue prefissate (/it/realizzazioni/... e
     * /en/case-studies/...), omettere il prefisso sulla lingua di default
     * è un raffinamento futuro, non escluso da questo modello.
     *
     * Il pattern del PATH (tutto tranne il prefisso lingua) si risolve in
     * ordine: (1) override per (content_type, language) — Decisione #2,
     * "può essere sovrascritto per lingua" — ContentTypeService::
     * setPermalinkPatternOverride(); (2) pattern di default del Content
     * Type (content_types.permalink_pattern); (3) fallback calcolato
     * /{content_type.slug}/{slug} se nessuno dei due è configurato.
     */
    private function buildPermalink(array $contentType, array $language, string $slug): string
    {
        $pattern = $this->resolvePathPattern((int) $contentType['id'], (int) $language['id'], $contentType);
        $path    = str_replace('{slug}', $slug, $pattern);

        return '/' . trim($language['code'] . '/' . ltrim($path, '/'), '/');
    }

    private function resolvePathPattern(int $contentTypeId, int $languageId, array $contentType): string
    {
        $override = $this->permalinkPatternRepository->find($contentTypeId, $languageId);
        if ($override) {
            return $override['pattern'];
        }

        return $contentType['permalink_pattern'] ?: ('/' . $contentType['slug'] . '/{slug}');
    }

    private function ensureUniqueSlug(int $contentTypeId, int $languageId, string $candidateText, int $excludeId): string
    {
        $base = $this->slugify($candidateText);
        $slug = $base;
        $i    = 2;

        while ($this->translationRepository->slugExists($contentTypeId, $languageId, $slug, $excludeId)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'n-' . substr(md5(uniqid('', true)), 0, 8);
    }
}
