<?php

namespace Giga\Cms\FieldTypes;

class BooleanFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'boolean';
    }

    public function schema(): array
    {
        return ['key' => 'boolean', 'label' => 'Booleano', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        // Qualunque valore è accettabile: normalizzato a bool in persist().
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_boolean' => (int) (bool) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_boolean'] !== null ? (bool) $valueRow['value_boolean'] : null;
    }
}
