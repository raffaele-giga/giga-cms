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
 *   - external/email/phone/anchor -> value_text (singole stringhe, nessuna
 *                                    struttura composita: non serve
 *                                    nemmeno il JSON)
 *   - file                        -> value_media_id, MA non ancora
 *                                    supportato qui: richiede l'Asset
 *                                    Library (Media/Gallery), che non
 *                                    esiste ancora e quindi non ha ancora
 *                                    una FK su value_media_id. validate()
 *                                    rifiuta esplicitamente questo kind
 *                                    invece di scriverlo senza protezione
 *                                    — si sblocca quando Media/Gallery
 *                                    arriva, stesso pattern già usato per
 *                                    la FK di field_id (differita fino a
 *                                    quando fields è esistita).
 *
 * value_variant (content_entry_values) è il discriminatore: dice quale
 * kind è attivo per la riga, dato che la colonna valorizzata cambia in
 * base al kind. Nessun kind qui usa value_json (Invariante #3 resta
 * un'eccezione per compositi realmente non relazionali, non una
 * scorciatoia per evitare di modellare un discriminatore).
 */
class LinkFieldType implements FieldTypeInterface
{
    private const KNOWN_KINDS = ['entry', 'external', 'email', 'phone', 'anchor', 'file'];

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
        if ($kind === 'file') {
            throw new \RuntimeException(
                "kind 'file' non ancora supportato: richiede l'Asset Library (Media/Gallery), non ancora costruita."
            );
        }
        if (!isset($rawValue['value']) || $rawValue['value'] === '') {
            throw new \InvalidArgumentException('value è obbligatorio.');
        }
        if ($kind === 'entry' && (!is_numeric($rawValue['value']) || (int) $rawValue['value'] <= 0)) {
            throw new \InvalidArgumentException("Per kind 'entry', value deve essere un id di entry positivo.");
        }
    }

    public function persist(mixed $rawValue, array $fieldConfig): array
    {
        $kind = $rawValue['kind'];

        if ($kind === 'entry') {
            return ['value_variant' => 'entry', 'value_entry_id' => (int) $rawValue['value']];
        }

        return ['value_variant' => $kind, 'value_text' => (string) $rawValue['value']];
    }

    public function render(array $valueRow, array $fieldConfig): mixed
    {
        $kind = $valueRow['value_variant'] ?? null;
        if ($kind === null) {
            return null;
        }

        return [
            'kind'  => $kind,
            'value' => $kind === 'entry' ? (int) $valueRow['value_entry_id'] : $valueRow['value_text'],
        ];
    }
}
