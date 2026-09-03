<?php

namespace Giga\Cms\Theme;

use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\MediaRepository;
use Giga\Cms\FieldTypes\FieldTypeRegistry;

/**
 * Risoluzione dei Custom Field condivisa tra ContentEntryPresenter e
 * BlockPresenter — stessa identica logica di render (via
 * FieldTypeInterface), scoping per lingua e arricchimento dei field
 * media, non duplicata tra i due Presenter che la usano su liste di
 * values diverse (getValues($entryId) vs getBlockValues($blockId)).
 */
class FieldValueResolver
{
    public function __construct(
        private FieldRepository $fieldRepository,
        private MediaRepository $mediaRepository,
        private FieldTypeRegistry $fieldTypeRegistry
    ) {
    }

    /** @param array $values righe di content_entry_values (dirette di un'entry, o scoped a un blocco) */
    public function resolve(array $values, array $language): array
    {
        $result = [];

        foreach ($values as $value) {
            $field = $this->fieldRepository->findById((int) $value['field_id']);
            if (!$field || !$this->belongsToCurrentLanguage($field, $value, $language)) {
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

        return $result;
    }

    private function belongsToCurrentLanguage(array $field, array $value, array $language): bool
    {
        $languageId = $value['language_id'] !== null ? (int) $value['language_id'] : null;

        if ($field['translatable']) {
            return $languageId === (int) $language['id'];
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
