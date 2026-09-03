<?php

namespace Giga\Cms\Theme;

use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\ContentEntryBlockRepository;
use Giga\Cms\Repositories\ContentEntryStatRepository;
use Giga\Cms\Repositories\TaxonomyRepository;
use Giga\Cms\Services\ContentEntryTranslationService;

/**
 * Metà "Presenter" del Contratto Theme↔CMS (Decisione #3). Nessuna
 * proprietà magica per i Custom Field: solo title/slug/template sono
 * proprietà dirette perché modellati esplicitamente come campi core
 * (content_entry_translations/content_entries) — ogni Custom Field,
 * incluso uno destinato a fare da "immagine principale", passa
 * uniformemente da fields[$key].
 *
 * fields/taxonomy()/blocks() fanno ciascuno una query aggiuntiva la prima
 * volta che vengono letti (N+1 su una lista di molte entry) —
 * accettabile per V1: "cache opzionale e trasparente" è un miglioramento
 * futuro esplicito (Principi trasversali), non una promessa di questo giro.
 */
class ContentEntryPresenter
{
    private ?array $resolvedFields = null;
    private ?array $resolvedBlocks = null;

    public function __construct(
        private array $row,
        private array $language,
        private ContentEntryRepository $entryRepository,
        private ContentEntryBlockRepository $blockRepository,
        private ContentEntryStatRepository $statRepository,
        private TaxonomyRepository $taxonomyRepository,
        private FieldValueResolver $fieldValueResolver,
        private ContentEntryTranslationService $translationService
    ) {
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'id'          => (int) $this->row['id'],
            'title'       => $this->row['title'],
            'slug'        => $this->row['slug'],
            'template'    => $this->row['template'],
            'is_featured' => (bool) $this->row['is_featured'],
            'fields'      => $this->resolveFields(),
            default       => throw new \RuntimeException(
                "Proprietà '{$name}' non esposta dal Contratto Theme↔CMS."
            ),
        };
    }

    /** @return array<int, array{slug: string, label: string}> */
    public function taxonomy(string $taxonomySlug): array
    {
        $taxonomy = $this->taxonomyRepository->findBySlug($taxonomySlug);
        if (!$taxonomy) {
            return [];
        }

        $terms = $this->entryRepository->getTerms((int) $this->row['id'], (int) $taxonomy['id']);

        return array_map(
            fn(array $term) => ['slug' => $term['slug'], 'label' => $term['label']],
            $terms
        );
    }

    /** Statistiche di lettura pubblica (Content Type supports_stats). */
    public function stats(): ContentEntryStatsPresenter
    {
        return new ContentEntryStatsPresenter($this->statRepository->find((int) $this->row['id']));
    }

    /**
     * Meta SEO con gli stessi fallback di ContentEntryTranslationService::
     * getSeoMeta() (meta_title→title, canonical_url calcolato, robots da
     * indexable+follow) — il tema non deve reimplementarli né chiamare
     * il Service direttamente (Decisione #3).
     *
     * @return array{meta_title:?string, meta_description:?string, canonical_url:string, robots:string, og_title:?string, og_description:?string, og_media_id:?int, json_ld_type:?string}
     */
    public function seo(): array
    {
        return $this->translationService->getSeoMeta((int) $this->row['id'], (int) $this->language['id']);
    }

    /**
     * Permalink reale (permalink_pattern, override per lingua, fallback
     * calcolato) — il tema non deve mai ricostruire l'URL a mano da
     * slug/content_type, userebbe la stessa logica duplicata invece che
     * la fonte unica già costruita per questo (Decisione #2).
     */
    public function permalink(): string
    {
        return $this->translationService->getPermalink((int) $this->row['id'], (int) $this->language['id']);
    }

    /**
     * Istanze di blocco della entry (Page Builder), ordinate. Un tema
     * risolve ->type (lo slug del Block Type) a un template proprio
     * (es. themes/current/blocks/{$block->type}.php) — quella
     * risoluzione resta al Theme, non a questo Presenter.
     *
     * @return BlockPresenter[]
     */
    public function blocks(): array
    {
        if ($this->resolvedBlocks !== null) {
            return $this->resolvedBlocks;
        }

        $blocks = $this->blockRepository->findByEntry((int) $this->row['id']);

        return $this->resolvedBlocks = array_map(
            fn(array $block) => new BlockPresenter($block, $this->language, $this->entryRepository, $this->fieldValueResolver),
            $blocks
        );
    }

    private function resolveFields(): array
    {
        if ($this->resolvedFields !== null) {
            return $this->resolvedFields;
        }

        $values = $this->entryRepository->getValues((int) $this->row['id']);

        return $this->resolvedFields = $this->fieldValueResolver->resolve($values, $this->language);
    }
}
