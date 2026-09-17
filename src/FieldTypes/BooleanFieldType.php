<?php

namespace Giga\Cms\FieldTypes;

class BooleanFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'boolean';
    }

    public function schema(): array
    {
        return ['key' => 'boolean', 'label' => 'Booleano', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        // Qualunque valore è accettabile: normalizzato a bool in persist().
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_boolean' => (int) (bool) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_boolean'] !== null ? (bool) $valueRow['value_boolean'] : null;
    }

    /**
     * Nessuna classe $ui applicata qui, deliberatamente — deviazione dal
     * fallback filter_input usato per gli altri Field Type senza token
     * dedicato: il solo riferimento reale trovato per un checkbox singolo
     * (non toggle-switch) è users/create.php del pacchetto
     * (<input type="checkbox" name="is_active" ...>, ZERO classi) —
     * applicare filter_input (bordo/padding pensati per un box testuale)
     * produrrebbe un checkbox racchiuso in un rettangolo bordato, un difetto
     * visivo reale, non un'approssimazione accettabile. L'unico token dedicato
     * esistente per un checkbox stilizzato (toggle_switch/toggle_switch_label,
     * vedi ui.php) richiede una struttura a 3 elementi (label wrapper + div
     * sibling), incompatibile con l'invariante "renderInput() produce un solo
     * elemento di input grezzo" — non applicabile senza cambiare quel
     * contratto, fuori scope qui.
     */
    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $name    = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id      = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');
        $checked = (bool) $context->value ? ' checked' : '';

        return "<input type=\"checkbox\" name=\"{$name}\" id=\"{$id}\" value=\"1\"{$checked}>";
    }
}
