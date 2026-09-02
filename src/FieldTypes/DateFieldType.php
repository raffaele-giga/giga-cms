<?php

namespace Giga\Cms\FieldTypes;

class DateFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'date';
    }

    public function schema(): array
    {
        return ['key' => 'date', 'label' => 'Data', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_string($rawValue) || \DateTime::createFromFormat('Y-m-d', $rawValue) === false) {
            throw new \InvalidArgumentException('La data deve essere in formato Y-m-d.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_date' => $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_date'] ?? null;
    }
}
