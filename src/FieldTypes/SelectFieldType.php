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

    /**
     * Nessuna opzione selezionata è un default "vuoto" legittimo qui
     * (coerente con $context->value nullable) — un <option value=""> vuoto
     * in cima, non una delle opzioni reali scelta a caso. Fallisce
     * rumorosamente (non un <select> vuoto silenzioso) se il field non
     * dichiara nessuna opzione in config: stesso caso già bloccato da
     * validate(), qui un errore di configurazione, non di input utente.
     */
    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $options = $fieldConfig['options'] ?? null;
        if (!is_array($options) || $options === []) {
            throw new \LogicException("renderInput: il field select non dichiara nessuna opzione in config.options.");
        }

        $name = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id   = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');

        // Classe hardcoded, stesso valore letterale di $ui['select'] (token
        // dedicato esistente per <select> — vedi il commento in
        // TextFieldType::renderInput() per il motivo dell'hardcoding).
        $class = 'appearance-none rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 pr-10 text-sm text-gray-700 dark:text-gray-200 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 hover:border-gray-300 dark:hover:border-gray-600 transition-colors w-full';

        $html = "<select name=\"{$name}\" id=\"{$id}\" class=\"{$class}\">";
        $html .= '<option value="">— seleziona —</option>';
        foreach ($options as $option) {
            $optionEscaped = htmlspecialchars((string) $option, ENT_QUOTES, 'UTF-8');
            $selected      = $context->value !== null && (string) $context->value === (string) $option ? ' selected' : '';
            $html .= "<option value=\"{$optionEscaped}\"{$selected}>{$optionEscaped}</option>";
        }
        $html .= '</select>';

        return $html;
    }
}
