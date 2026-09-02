<?php

namespace Giga\Cms\FieldTypes;

/** min/max opzionali in fields.config, es. {"min": 0, "max": 100}. */
class NumberFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'number';
    }

    public function schema(): array
    {
        return ['key' => 'number', 'label' => 'Numero', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_numeric($rawValue)) {
            throw new \InvalidArgumentException('Il valore deve essere numerico.');
        }
        if (isset($fieldConfig['min']) && $rawValue < $fieldConfig['min']) {
            throw new \InvalidArgumentException("Il valore deve essere >= {$fieldConfig['min']}.");
        }
        if (isset($fieldConfig['max']) && $rawValue > $fieldConfig['max']) {
            throw new \InvalidArgumentException("Il valore deve essere <= {$fieldConfig['max']}.");
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_number' => (float) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_number'] !== null ? (float) $valueRow['value_number'] : null;
    }
}
