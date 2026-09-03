<?php

namespace Giga\Cms\Theme;

use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\TaxonomyRepository;
use Giga\Cms\Repositories\TaxonomyTermRepository;
use Giga\Cms\Repositories\MediaRepository;
use Giga\Cms\Repositories\LanguageRepository;
use Giga\Cms\FieldTypes\FieldTypeRegistry;

/**
 * Unico punto di ingresso del Contratto Theme↔CMS (Decisione #3,
 * Invariante #5). Un tema non deve mai istanziare un Repository o un
 * Model direttamente — solo Cms, il ContentQuery che restituisce, e i
 * ContentEntryPresenter che ne escono.
 *
 * Uso:
 *   $cms = new Cms();
 *   $cms->content('projects')->published()->featured()->limit(6)->get();
 */
class Cms
{
    private ContentTypeRepository $typeRepository;
    private ContentEntryRepository $entryRepository;
    private FieldRepository $fieldRepository;
    private TaxonomyRepository $taxonomyRepository;
    private TaxonomyTermRepository $termRepository;
    private MediaRepository $mediaRepository;
    private LanguageRepository $languageRepository;
    private FieldTypeRegistry $fieldTypeRegistry;

    public function __construct()
    {
        $this->typeRepository     = new ContentTypeRepository();
        $this->entryRepository    = new ContentEntryRepository();
        $this->fieldRepository    = new FieldRepository();
        $this->taxonomyRepository = new TaxonomyRepository();
        $this->termRepository     = new TaxonomyTermRepository();
        $this->mediaRepository    = new MediaRepository();
        $this->languageRepository = new LanguageRepository();
        $this->fieldTypeRegistry  = new FieldTypeRegistry();
    }

    public function content(string $contentTypeSlug): ContentQuery
    {
        $contentType = $this->typeRepository->findBySlug($contentTypeSlug);
        if (!$contentType) {
            throw new \RuntimeException("Content Type '{$contentTypeSlug}' non trovato.");
        }

        $language = $this->languageRepository->findDefault();
        if (!$language) {
            throw new \RuntimeException('Nessuna lingua di default configurata.');
        }

        return new ContentQuery(
            $contentType,
            $language,
            $this->entryRepository,
            $this->fieldRepository,
            $this->taxonomyRepository,
            $this->termRepository,
            $this->mediaRepository,
            $this->languageRepository,
            $this->fieldTypeRegistry
        );
    }
}
