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

    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $value = htmlspecialchars((string) ($context->value ?? ''), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id    = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');

        // Classe hardcoded, stesso valore letterale di $ui['filter_input'] —
        // vedi il commento in TextFieldType::renderInput() per il motivo.
        $class = 'text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary w-full';

        return "<input type=\"date\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\" class=\"{$class}\">";
    }
}
