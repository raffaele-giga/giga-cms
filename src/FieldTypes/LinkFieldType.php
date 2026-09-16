<?php

namespace Giga\Cms\FieldTypes;

/**
 * Link/CTA con kind variabile per-valore (Content Engine, "link/CTA: URL
 * esterno, entry interna, file, email, telefono, anchor").
 *
 * Ogni kind persiste nella colonna tipizzata corretta, mai in value_json:
 * un riferimento a un'altra entry o a un media non può perdere la
 * protezione FK per comodità di modellazione.
 *   - entry                       -> value_entry_id (FK, cardinalità 1,
 *                                    stessa colonna/protezione di
 *                                    RelationFieldType)
 *   - file                        -> value_media_id (FK, cardinalità 1,
 *                                    stessa colonna/protezione di
 *                                    MediaFieldType — sbloccato ora che
 *                                    Media/Gallery esiste: prima la FK su
 *                                    value_media_id non c'era ancora)
 *   - external/email/phone/anchor -> value_text (singole stringhe, nessuna
 *                                    struttura composita: non serve
 *                                    nemmeno il JSON)
 *
 * value_variant (content_entry_values) è il discriminatore: dice quale
 * kind è attivo per la riga, dato che la colonna valorizzata cambia in
 * base al kind. Nessun kind qui usa value_json (Invariante #3 resta
 * un'eccezione per compositi realmente non relazionali, non una
 * scorciatoia per evitare di modellare un discriminatore).
 */
class LinkFieldType implements FieldTypeInterface
{
    private const KNOWN_KINDS = ['entry', 'file', 'external', 'email', 'phone', 'anchor'];

    public function key(): string
    {
        return 'link';
    }

    public function schema(): array
    {
        return ['key' => 'link', 'label' => 'Link / CTA', 'uses_value_json' => false];
    }

    public function validate(mixed $rawValue, array $fieldConfig): void
    {
        if (!is_array($rawValue)) {
            throw new \InvalidArgumentException("Il valore deve essere un array ['kind', 'value'].");
        }

        $kind = $rawValue['kind'] ?? null;
        if (!in_array($kind, self::KNOWN_KINDS, true)) {
            throw new \InvalidArgumentException('kind non valido, atteso uno tra: ' . implode(', ', self::KNOWN_KINDS));
        }
        if (!isset($rawValue['value']) || $rawValue['value'] === '') {
            throw new \InvalidArgumentException('value è obbligatorio.');
        }
        if (($kind === 'entry' || $kind === 'file') && (!is_numeric($rawValue['value']) || (int) $rawValue['value'] <= 0)) {
            throw new \InvalidArgumentException("Per kind '{$kind}', value deve essere un id positivo.");
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        $kind = $rawValue['kind'];

        return match ($kind) {
            'entry' => ['value_variant' => 'entry', 'value_entry_id' => (int) $rawValue['value']],
            'file'  => ['value_variant' => 'file', 'value_media_id' => (int) $rawValue['value']],
            default => ['value_variant' => $kind, 'value_text' => (string) $rawValue['value']],
        };
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        $kind = $valueRow['value_variant'] ?? null;
        if ($kind === null) {
            return null;
        }

        $value = match ($kind) {
            'entry' => (int) $valueRow['value_entry_id'],
            'file'  => (int) $valueRow['value_media_id'],
            default => $valueRow['value_text'],
        };

        return ['kind' => $kind, 'value' => $value];
    }

    /** Non ancora usato da nessun Content Type reale — fallisce esplicitamente invece di un fallback silenzioso. */
    public function renderInput(FieldInputContext $context, array $fieldConfig): string
    {
        throw new \LogicException('renderInput non ancora implementato per link');
    }
}
