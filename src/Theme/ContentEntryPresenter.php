<?php

namespace Giga\Cms\Theme;

use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\TaxonomyRepository;
use Giga\Cms\Repositories\MediaRepository;
use Giga\Cms\FieldTypes\FieldTypeRegistry;

/**
 * Metà "Presenter" del Contratto Theme↔CMS (Decisione #3). Nessuna
 * proprietà magica per i Custom Field: solo title/slug/template sono
 * proprietà dirette perché modellati esplicitamente come campi core
 * (content_entry_translations/content_entries) — ogni Custom Field,
 * incluso uno destinato a fare da "immagine principale", passa
 * uniformemente da fields[$key].
 *
 * fields/taxonomy() fanno ciascuno una query aggiuntiva la prima volta
 * che vengono letti (N+1 su una lista di molte entry) — accettabile per
 * V1: "cache opzionale e trasparente" è un miglioramento futuro
 * esplicito (Principi trasversali), non una promessa di questo giro.
 *
 * Un field di tipo 'link' il cui kind è 'entry'/'file' espone il solo id
 * grezzo (entry_id/media_id) in value — non risolto ricorsivamente in un
 * secondo Presenter/descrittore media. Solo i field di tipo 'media'
 * ottengono la risoluzione arricchita (path/alt/title), perché è il caso
 * "immagine" esplicitamente citato dal documento; approfondire gli altri
 * casi resta un'estensione futura, non necessaria qui.
 */
class ContentEntryPresenter
{
    private array $row;
    private array $language;
    private ContentEntryRepository $entryRepository;
    private FieldRepository $fieldRepository;
    private TaxonomyRepository $taxonomyRepository;
    private MediaRepository $mediaRepository;
    private FieldTypeRegistry $fieldTypeRegistry;
    private ?array $resolvedFields = null;

    public function __construct(
        array $row,
        array $language,
        ContentEntryRepository $entryRepository,
        FieldRepository $fieldRepository,
        TaxonomyRepository $taxonomyRepository,
        MediaRepository $mediaRepository,
        FieldTypeRegistry $fieldTypeRegistry
    ) {
        $this->row                = $row;
        $this->language           = $language;
        $this->entryRepository    = $entryRepository;
        $this->fieldRepository    = $fieldRepository;
        $this->taxonomyRepository = $taxonomyRepository;
        $this->mediaRepository    = $mediaRepository;
        $this->fieldTypeRegistry  = $fieldTypeRegistry;
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

    private function resolveFields(): array
    {
        if ($this->resolvedFields !== null) {
            return $this->resolvedFields;
        }

        $result = [];

        foreach ($this->entryRepository->getValues((int) $this->row['id']) as $value) {
            $field = $this->fieldRepository->findById((int) $value['field_id']);
            if (!$field || !$this->belongsToCurrentLanguage($field, $value)) {
                continue;
            }
            if (!$this->fieldTypeRegistry->has($field['type'])) {
                continue;
            }

            $fieldConfig = $field['config'] !== null ? json_decode($field['config'], true) : [];
            $rendered    = $this->fieldTypeRegistry->get($field['type'])->render($value, $fieldConfig);

            if ($field['type'] === 'media' && $rendered !== null) {
                $rendered = $this->resolveMedia((int) $rendered);
            }

            $this->appendFieldValue($result, $field['key'], $rendered);
        }

        return $this->resolvedFields = $result;
    }

    private function belongsToCurrentLanguage(array $field, array $value): bool
    {
        $languageId = $value['language_id'] !== null ? (int) $value['language_id'] : null;

        if ($field['translatable']) {
            return $languageId === (int) $this->language['id'];
        }

        return $languageId === null;
    }

    /** Un field ripetuto (più righe per lo stesso field_id, es. repeater) diventa un array di valori. */
    private function appendFieldValue(array &$result, string $key, mixed $value): void
    {
        if (!array_key_exists($key, $result)) {
            $result[$key] = $value;
            return;
        }
        if (!is_array($result[$key]) || !array_is_list($result[$key])) {
            $result[$key] = [$result[$key]];
        }
        $result[$key][] = $value;
    }

    private function resolveMedia(int $mediaId): ?array
    {
        $media = $this->mediaRepository->findById($mediaId);
        if (!$media) {
            return null;
        }

        return [
            'path'      => $media['path'],
            'alt'       => $media['alt'],
            'title'     => $media['title'],
            'mime_type' => $media['mime_type'],
            'kind'      => $media['kind'],
        ];
    }
}
