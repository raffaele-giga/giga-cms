<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Discriminatore per i valori traducibili (Modello multilingua):
 * content_entry_translations "contiene... i Custom Field marcati
 * translatable: true" — questa è la colonna che li distingue dai valori
 * globali. NULL = valore condiviso da tutte le lingue (field non
 * traducibile); valorizzato = variante per quella lingua (una riga per
 * lingua attiva). La coerenza fields.translatable <-> language_id non è
 * un vincolo DB: la valida ContentEntryService, stesso approccio già
 * usato per value_json/tipo dichiarato del field.
 *
 * Migration additiva: le righe esistenti restano con language_id NULL,
 * corretto perché finora nessun field creato nei test era translatable.
 *
 * RESTRICT: coerente con field_id/value_media_id — non si può cancellare
 * una lingua ancora referenziata da valori esistenti.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values
                ADD COLUMN language_id INT UNSIGNED NULL AFTER field_id,
                ADD KEY idx_content_entry_values_language (language_id),
                ADD CONSTRAINT fk_content_entry_values_language
                    FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE RESTRICT"
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values
                DROP FOREIGN KEY fk_content_entry_values_language,
                DROP KEY idx_content_entry_values_language,
                DROP COLUMN language_id"
        );
    }
};
