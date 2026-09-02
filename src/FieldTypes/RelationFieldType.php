<?php

namespace Giga\Cms\FieldTypes;

/**
 * Cardinalità 1 soltanto — coerente con Decisione #1 (value_entry_id copre
 * solo cardinalità 1; le relazioni 1:N/N:N useranno content_entry_relations,
 * non ancora costruita — fuori scope per questo tipo).
 *
 * L'esistenza dell'entry referenziata non è verificata qui: lo fa già la FK
 * content_entry_values.value_entry_id -> content_entries (ON DELETE SET
 * NULL) a livello DB — nessuna query duplicata in un componente che per
 * design non dipende da Database.
 */
class RelationFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'relation';
    }

    public function schema(): array
    {
        return ['key' => 'relation', 'label' => 'Relazione (1:1)', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_numeric($rawValue) || (int) $rawValue <= 0) {
            throw new \InvalidArgumentException('Il valore deve essere un id di entry positivo.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_entry_id' => (int) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_entry_id'] !== null ? (int) $valueRow['value_entry_id'] : null;
    }
}
