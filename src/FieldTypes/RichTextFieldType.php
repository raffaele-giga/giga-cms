<?php

namespace Giga\Cms\FieldTypes;

/**
 * Persiste come testo grezzo, senza sanitizzazione HTML — nessun
 * sanitizzatore è ancora integrato in giga-cms. Non trattare questo tipo
 * come un confine di sicurezza: l'escaping/sanitizzazione in output resta
 * responsabilità del layer di rendering (Principi trasversali, "escaping
 * output"), da implementare quando esisterà un editor rich text reale.
 */
class RichTextFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'richtext';
    }

    public function schema(): array
    {
        return ['key' => 'richtext', 'label' => 'Rich Text', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_string($rawValue)) {
            throw new \InvalidArgumentException('Il valore deve essere una stringa HTML.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_text' => $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_text'] ?? null;
    }

    /**
     * Semplice <textarea> — nessun editor rich text integrato (vedi
     * docblock di classe: nessun sanitizzatore HTML ancora presente in
     * giga-cms). Il contenuto HTML grezzo va nel body del textarea, non
     * nell'attributo value: htmlspecialchars() qui evita che un valore già
     * contenente HTML chiuda prematuramente il tag.
     */
    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $value = htmlspecialchars((string) ($context->value ?? ''), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id    = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');

        // filter_input, non un token textarea dedicato: non esiste (verificato
        // in ui.php), ma è anche il valore reale già usato per i <textarea> nei
        // riferimenti di progetto (es. wos-pro reminders/create.php, campo
        // "Note") — non un fallback generico, coincide col precedente reale.
        $class = 'text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-primary w-full';

        return "<textarea name=\"{$name}\" id=\"{$id}\" class=\"{$class}\">{$value}</textarea>";
    }
}
