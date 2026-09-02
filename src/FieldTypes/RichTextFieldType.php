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
}
