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

    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $value = htmlspecialchars((string) ($context->value ?? ''), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id    = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');

        // Classe hardcoded (stesso valore letterale del token $ui['filter_input']
        // di giga-admin-shell), non UiTokens::load(): giga-cms non dipende da
        // giga-admin-shell (verificato nei rispettivi composer.json, nessuna
        // relazione diretta tra i due pacchetti oggi) — introdurla qui la
        // creerebbe per la prima volta, rompendo l'indipendenza attuale.
        $class = 'text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary w-full';

        return "<input type=\"text\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\" class=\"{$class}\">";
    }
}
