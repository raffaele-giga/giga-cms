<?php

namespace Giga\Cms\FieldTypes;

/**
 * Riferimento singolo a un asset (cardinalità 1) — coerente con Decisione
 * #1 (value_media_id copre solo cardinalità 1; le gallery a cardinalità
 * multipla usano content_entry_media, gestita a parte da
 * ContentEntryService::getGallery()/replaceGallery(), fuori dal Field Type
 * registry perché non produce una riga di content_entry_values).
 *
 * L'esistenza del media referenziato non è verificata qui: lo fa già la FK
 * content_entry_values.value_media_id -> media (ON DELETE SET NULL) a
 * livello DB — nessuna query duplicata in un componente che per design
 * non dipende da Database (stesso approccio di RelationFieldType).
 */
class MediaFieldType implements FieldTypeInterface
{
    public function key(): string
    {
        return 'media';
    }

    public function schema(): array
    {
        return ['key' => 'media', 'label' => 'Media (immagine/file singolo)', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_numeric($rawValue) || (int) $rawValue <= 0) {
            throw new \InvalidArgumentException('Il valore deve essere un id di media positivo.');
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        return ['value_media_id' => (int) $rawValue];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        return $valueRow['value_media_id'] !== null ? (int) $valueRow['value_media_id'] : null;
    }

    /**
     * Minimo per ora: solo l'hidden con l'id media corrente, più un testo
     * di stato — NON un vero file-picker (browser dell'Asset Library:
     * ricerca/anteprima/upload). Costruire quel picker è lavoro separato,
     * fuori scope qui — non bloccante per verificare il resto del motore
     * di renderInput() sugli altri 7 tipi. L'eccezione all'invariante
     * "solo l'elemento di input grezzo" (qui c'è anche uno <span> di testo)
     * è deliberata: senza un indicatore visivo minimo l'input hidden
     * sarebbe invisibile e la sua assenza di funzionalità reale
     * silenziosa, contro lo spirito "nessun fallback silenzioso".
     */
    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        $value = htmlspecialchars((string) ($context->value ?? ''), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($context->name, ENT_QUOTES, 'UTF-8');
        $id    = htmlspecialchars($context->id, ENT_QUOTES, 'UTF-8');

        $status = $context->value !== null
            ? 'media id corrente: ' . htmlspecialchars((string) $context->value, ENT_QUOTES, 'UTF-8')
            : 'nessun media selezionato';

        return "<input type=\"hidden\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\">"
            . "<span data-field-type=\"media-placeholder\">File-picker non ancora implementato (Asset Library) — {$status}</span>";
    }
}
