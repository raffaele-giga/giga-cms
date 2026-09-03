<?php

namespace Giga\Cms\Theme;

use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\ContentEntryBlockRepository;
use Giga\Cms\Repositories\TaxonomyRepository;
use Giga\Cms\Repositories\TaxonomyTermRepository;
use Giga\Cms\Repositories\LanguageRepository;

/**
 * Query service del Contratto Theme↔CMS (Decisione #3): "il tema non
 * decide autonomamente filtri/JOIN/criteri sui dati interni". Ogni
 * risultato passa da ContentEntryPresenter, mai una riga grezza.
 *
 * Immutabile per fluent-chaining nel senso comune (i metodi ritornano
 * $this, non un nuovo oggetto) — coerente con l'esempio del documento
 * ($cms->content('projects')->published()->featured()->limit(6)->get()),
 * non pensata per essere condivisa/riusata tra chiamate concorrenti.
 */
class ContentQuery
{
    private array $filters = [];
    private ?int $limitValue = null;
    private int $page = 1;

    public function __construct(
        private array $contentType,
        private array $language,
        private ContentEntryRepository $entryRepository,
        private ContentEntryBlockRepository $blockRepository,
        private TaxonomyRepository $taxonomyRepository,
        private TaxonomyTermRepository $termRepository,
        private LanguageRepository $languageRepository,
        private FieldValueResolver $fieldValueResolver
    ) {
    }

    /** Pubblico effettivo (ContentEntryRepository::effectivePublicCondition()) E traduzione esistente nella lingua corrente. */
    public function published(): static
    {
        $this->filters['effectively_public'] = true;
        return $this;
    }

    public function featured(): static
    {
        $this->filters['is_featured'] = true;
        return $this;
    }

    /** Cambia la lingua della query (default: lingua di default dell'installazione). */
    public function language(string $code): static
    {
        $language = $this->languageRepository->findByCode($code);
        if (!$language) {
            throw new \RuntimeException("Lingua '{$code}' non trovata.");
        }
        $this->language = $language;
        return $this;
    }

    /**
     * Filtra per un termine di tassonomia. Tassonomia/termine inesistenti
     * producono un filtro sempre-falso (nessun risultato) invece di
     * un'eccezione: una query di listing non dovrebbe rompersi per un
     * termine non ancora creato in un tema in sviluppo.
     */
    public function taxonomy(string $taxonomySlug, string $termSlug): static
    {
        $taxonomy = $this->taxonomyRepository->findBySlug($taxonomySlug);
        $term     = $taxonomy ? $this->termRepository->findBySlug((int) $taxonomy['id'], $termSlug) : false;

        $this->filters['term_id'] = $term ? (int) $term['id'] : 0;
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = max(1, $limit);
        return $this;
    }

    public function page(int $page): static
    {
        $this->page = max(1, $page);
        return $this;
    }

    /** @return ContentEntryPresenter[] */
    public function get(): array
    {
        $rows = $this->entryRepository->findForTheme(
            (int) $this->contentType['id'],
            (int) $this->language['id'],
            $this->filters,
            $this->page,
            $this->limitValue ?? 1000
        );

        return array_map(
            fn(array $row) => new ContentEntryPresenter(
                $row,
                $this->language,
                $this->entryRepository,
                $this->blockRepository,
                $this->taxonomyRepository,
                $this->fieldValueResolver
            ),
            $rows
        );
    }

    public function first(): ?ContentEntryPresenter
    {
        $previousLimit    = $this->limitValue;
        $this->limitValue = 1;
        $results          = $this->get();
        $this->limitValue = $previousLimit;

        return $results[0] ?? null;
    }

    public function count(): int
    {
        return $this->entryRepository->countForTheme(
            (int) $this->contentType['id'],
            (int) $this->language['id'],
            $this->filters
        );
    }
}
