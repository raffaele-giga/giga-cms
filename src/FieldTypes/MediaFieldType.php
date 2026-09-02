<?php

namespace Giga\Cms\FieldTypes;

/**
 * Riferimento singolo a un asset (cardinalità 1) — coerente con Decisione
 * #1 (value_media_id copre solo cardinalità 1; le gallery a cardinalità
 * multipla usano content_entry_media, gestita a parte da
 * ContentEntryService::getGallery()/replaceGallery(), fuori dal Field Type
 * registry perché non produce una riga di content_entry_values).
 *
 * L'esistenza del media referenziato non è verificata qui: lo fa già la FK
 * content_entry_values.value_media_id -> media (ON DELETE SET NULL) a
 * livello DB — nessuna query duplicata in un componente che per design
 * non dipende da Database (stesso approccio di RelationFieldType).
 */
class MediaFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'media';
    }

    public function schema(): array
    {
        return ['key' => 'media', 'label' => 'Media (immagine/file singolo)', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_numeric($rawValue) || (int) $rawValue <= 0) {
            throw new \InvalidArgumentException('Il valore deve essere un id di media positivo.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_media_id' => (int) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_media_id'] !== null ? (int) $valueRow['value_media_id'] : null;
    }
}
