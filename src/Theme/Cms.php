<?php

namespace Giga\Cms\Theme;

use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\ContentEntryBlockRepository;
use Giga\Cms\Repositories\ContentEntryStatRepository;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\TaxonomyRepository;
use Giga\Cms\Repositories\TaxonomyTermRepository;
use Giga\Cms\Repositories\MediaRepository;
use Giga\Cms\Repositories\LanguageRepository;
use Giga\Cms\Repositories\MenuRepository;
use Giga\Cms\Repositories\MenuItemRepository;
use Giga\Cms\Services\ContentEntryTranslationService;
use Giga\Cms\FieldTypes\FieldTypeRegistry;

/**
 * Unico punto di ingresso del Contratto Theme↔CMS (Decisione #3,
 * Invariante #5). Un tema non deve mai istanziare un Repository o un
 * Model direttamente — solo Cms, ciò che ne esce (ContentQuery,
 * ContentEntryPresenter, BlockPresenter), e l'array annidato di menu().
 *
 * Uso:
 *   $cms = new Cms();
 *   $cms->content('projects')->published()->featured()->limit(6)->get();
 *   $cms->menu('main-nav');
 */
class Cms
{
    private ContentTypeRepository $typeRepository;
    private ContentEntryRepository $entryRepository;
    private ContentEntryBlockRepository $blockRepository;
    private ContentEntryStatRepository $statRepository;
    private TaxonomyRepository $taxonomyRepository;
    private TaxonomyTermRepository $termRepository;
    private LanguageRepository $languageRepository;
    private MenuRepository $menuRepository;
    private MenuItemRepository $menuItemRepository;
    private ContentEntryTranslationService $translationService;
    private FieldValueResolver $fieldValueResolver;

    public function __construct()
    {
        $this->typeRepository     = new ContentTypeRepository();
        $this->entryRepository    = new ContentEntryRepository();
        $this->blockRepository    = new ContentEntryBlockRepository();
        $this->statRepository     = new ContentEntryStatRepository();
        $this->taxonomyRepository = new TaxonomyRepository();
        $this->termRepository     = new TaxonomyTermRepository();
        $this->languageRepository = new LanguageRepository();
        $this->menuRepository     = new MenuRepository();
        $this->menuItemRepository = new MenuItemRepository();
        $this->translationService = new ContentEntryTranslationService();
        $this->fieldValueResolver = new FieldValueResolver(
            new FieldRepository(),
            new MediaRepository(),
            new FieldTypeRegistry()
        );
    }

    public function content(string $contentTypeSlug): ContentQuery
    {
        $contentType = $this->typeRepository->findBySlug($contentTypeSlug);
        if (!$contentType) {
            throw new \RuntimeException("Content Type '{$contentTypeSlug}' non trovato.");
        }

        return new ContentQuery(
            $contentType,
            $this->defaultLanguage(),
            $this->entryRepository,
            $this->blockRepository,
            $this->statRepository,
            $this->taxonomyRepository,
            $this->termRepository,
            $this->languageRepository,
            $this->fieldValueResolver,
            $this->translationService
        );
    }

    /**
     * Unico punto di scrittura raggiungibile dal tema (Content Type
     * supports_stats): registra una visualizzazione pubblica. Nessuna
     * riga viene scritta per un'entry il cui Content Type ha
     * supports_stats=false — nessun accumulo di dati non voluto per i
     * tipi che non lo richiedono (es. "Team").
     *
     * Deliberatamente silenzioso (non lancia eccezioni) se l'entry non
     * esiste o il Content Type non supporta le statistiche: una
     * visualizzazione di pagina pubblica non deve mai fallire per questo.
     */
    public function recordView(int $entryId): void
    {
        $entry = $this->entryRepository->findById($entryId);
        if (!$entry) {
            return;
        }

        $contentType = $this->typeRepository->findById((int) $entry['content_type_id']);
        if (!$contentType || !$contentType['supports_stats']) {
            return;
        }

        $this->statRepository->recordView($entryId);
    }

    /**
     * Albero annidato di un menu (Navigation/Menu), pronto per il
     * rendering: ogni nodo è ['label', 'url', 'children']. url è già
     * risolto (permalink dell'entry per il kind 'entry', url/anchor
     * grezzo per gli altri) — il tema non deve mai interpretare
     * target_variant/target_entry_id da solo.
     *
     * Un target 'entry' privo di traduzione nella lingua corrente
     * produce url=null (Policy contenuto incompleto V1, nessun
     * fallback) invece di far fallire l'intero menu per un solo link
     * rotto.
     *
     * @return array<int, array{label: string, url: ?string, children: array}>
     */
    public function menu(string $menuSlug): array
    {
        $menu = $this->menuRepository->findBySlug($menuSlug);
        if (!$menu) {
            return [];
        }

        $items    = $this->menuItemRepository->findByMenu((int) $menu['id']);
        $language = $this->defaultLanguage();

        return $this->buildMenuTree($items, null, $language);
    }

    private function buildMenuTree(array $items, ?int $parentId, array $language): array
    {
        $branch = [];

        foreach ($items as $item) {
            $itemParentId = $item['parent_id'] !== null ? (int) $item['parent_id'] : null;
            if ($itemParentId !== $parentId) {
                continue;
            }

            $branch[] = [
                'label'    => $item['label'],
                'url'      => $this->resolveMenuItemUrl($item, $language),
                'children' => $this->buildMenuTree($items, (int) $item['id'], $language),
            ];
        }

        return $branch;
    }

    private function resolveMenuItemUrl(array $item, array $language): ?string
    {
        if ($item['target_variant'] !== 'entry') {
            return $item['target_url'];
        }
        if ($item['target_entry_id'] === null) {
            return null; // FK SET NULL: l'entry puntata è stata cancellata
        }

        try {
            return $this->translationService->getPermalink((int) $item['target_entry_id'], (int) $language['id']);
        } catch (\RuntimeException) {
            return null; // nessuna traduzione in questa lingua: link rotto per questa lingua, non un errore fatale
        }
    }

    private function defaultLanguage(): array
    {
        $language = $this->languageRepository->findDefault();
        if (!$language) {
            throw new \RuntimeException('Nessuna lingua di default configurata.');
        }
        return $language;
    }
}
