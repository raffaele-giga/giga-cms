<?php

namespace Giga\Cms\FieldTypes;

/**
 * Cardinalità 1 soltanto — coerente con Decisione #1 (value_entry_id copre
 * solo cardinalità 1; le relazioni 1:N/N:N useranno content_entry_relations,
 * non ancora costruita — fuori scope per questo tipo).
 *
 * L'esistenza dell'entry referenziata non è verificata qui: lo fa già la FK
 * content_entry_values.value_entry_id -> content_entries (ON DELETE SET
 * NULL) a livello DB — nessuna query duplicata in un componente che per
 * design non dipende da Database.
 */
class RelationFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'relation';
    }

    public function schema(): array
    {
        return ['key' => 'relation', 'label' => 'Relazione (1:1)', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_numeric($rawValue) || (int) $rawValue <= 0) {
            throw new \InvalidArgumentException('Il valore deve essere un id di entry positivo.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_entry_id' => (int) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_entry_id'] !== null ? (int) $valueRow['value_entry_id'] : null;
    }

    /**
     * Riceve le opzioni già risolte, non le risolve da sé: coerente con
     * l'invariante "nessuna dipendenza da Database" di FieldTypeInterface
     * (violata da una prima versione di questo metodo che interrogava
     * ContentTypeService/ContentEntryService direttamente — corretto qui,
     * Opzione B). Chi chiama renderInput() (in futuro
     * ContentEntryFormService, non ancora scritto) è responsabile di
     * popolare $fieldConfig['relation_options'] con una query una tantum
     * per tutti i field Relation di un Content Type, non una per campo.
     *
     * $fieldConfig['relation_options']: array di ['id' => int, 'label' =>
     * string] — la label è già risolta dal chiamante (es. dal campo
     * 'title'/'name' dell'entry target), niente più il placeholder "#{id}"
     * della versione precedente.
     *
     * Nessuna opzione disponibile è uno stato legittimo (es. il content
     * type target non ha ancora entry) — non un errore di configurazione:
     * niente eccezione, si renderizza una <select> con una sola opzione
     * disabilitata.
     */
    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $options = $fieldConfig['relation_options'] ?? [];

        $name = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id   = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');

        // Classe hardcoded, stesso valore letterale di $ui['select'] — vedi
        // il commento in TextFieldType::renderInput() per il motivo.
        $class = 'appearance-none rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 pr-10 text-sm text-gray-700 dark:text-gray-200 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 hover:border-gray-300 dark:hover:border-gray-600 transition-colors w-full';

        if ($options === []) {
            return "<select name=\"{$name}\" id=\"{$id}\" class=\"{$class}\"><option value=\"\" disabled selected>Nessuna opzione disponibile</option></select>";
        }

        $html = "<select name=\"{$name}\" id=\"{$id}\" class=\"{$class}\">";
        $html .= '<option value="">— nessuna selezione —</option>';
        foreach ($options as $option) {
            $optionId    = (int) $option['id'];
            $optionLabel = htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8');
            $selected    = $context->value !== null && (int) $context->value === $optionId ? ' selected' : '';
            $html .= "<option value=\"{$optionId}\"{$selected}>{$optionLabel}</option>";
        }
        $html .= '</select>';

        return $html;
    }
}
