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

    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $value = htmlspecialchars((string) ($context->value ?? ''), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id    = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');

        $minAttr = isset($fieldConfig['min']) ? ' min="' . htmlspecialchars((string) $fieldConfig['min'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $maxAttr = isset($fieldConfig['max']) ? ' max="' . htmlspecialchars((string) $fieldConfig['max'], ENT_QUOTES, 'UTF-8') . '"' : '';

        // Classe hardcoded, stesso valore letterale di $ui['filter_input'] —
        // vedi il commento in TextFieldType::renderInput() per il motivo
        // (nessuna dipendenza nuova da giga-admin-shell).
        $class = 'text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary w-full';

        return "<input type=\"number\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\"{$minAttr}{$maxAttr} step=\"any\" class=\"{$class}\">";
    }
}
