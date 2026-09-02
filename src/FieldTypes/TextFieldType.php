<?php

namespace Giga\Cms\FieldTypes;

class TextFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'text';
    }

    public function schema(): array
    {
        return ['key' => 'text', 'label' => 'Testo', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_string($rawValue) && !is_numeric($rawValue)) {
            throw new \InvalidArgumentException('Il valore deve essere una stringa.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_text' => (string) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_text'] ?? null;
    }
}
