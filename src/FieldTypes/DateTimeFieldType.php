<?php

namespace Giga\Cms\FieldTypes;

class DateTimeFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'datetime';
    }

    public function schema(): array
    {
        return ['key' => 'datetime', 'label' => 'Data e ora', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_string($rawValue) || \DateTime::createFromFormat('Y-m-d H:i:s', $rawValue) === false) {
            throw new \InvalidArgumentException('Il datetime deve essere in formato Y-m-d H:i:s.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_datetime' => $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_datetime'] ?? null;
    }
}
