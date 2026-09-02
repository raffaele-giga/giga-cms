<?php

namespace Giga\Cms\FieldTypes;

/**
 * Le opzioni valide sono dichiarate in fields.config, es.
 * {"options": ["manufacturing", "creative"]} — config è metadato di
 * definizione del field, non un valore di un'istanza (Content Engine,
 * "Custom Fields"), non soggetto alla restrizione su
 * content_entry_values.value_json.
 */
class SelectFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'select';
    }

    public function schema(): array
    {
        return ['key' => 'select', 'label' => 'Select / Enum', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        $options = $fieldConfig['options'] ?? null;
        if (!is_array($options) || $options === []) {
            throw new \InvalidArgumentException('Il field select non dichiara nessuna opzione in config.options.');
        }
        if (!in_array($rawValue, $options, true)) {
            throw new \InvalidArgumentException('Valore non tra le opzioni ammesse: ' . implode(', ', $options));
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
