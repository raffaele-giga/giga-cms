<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Scoping per istanza di blocco (Page Builder), deciso insieme all'utente
 * per riusare l'intero meccanismo esistente (FieldTypeInterface,
 * translatable/language_id, validazione value_json) invece di duplicare
 * la struttura a colonne tipizzate in una tabella parallela.
 *
 * block_id NULL (comportamento invariato, tutte le righe esistenti lo
 * sono) = valore diretto su un'entry, come oggi. block_id valorizzato =
 * valore scoped a quella specifica istanza di blocco — permette allo
 * stesso field_id di avere valori diversi in istanze di blocco diverse
 * sulla stessa entry (es. il campo "heading" di ciascuno dei 3 blocchi
 * "hero" di una Page).
 *
 * CASCADE: cancellare un'istanza di blocco (content_entry_blocks) ne
 * cancella i valori — non ha senso lasciarli orfani, a differenza dei
 * casi "valore su un'entry" (SET NULL) dove il valore stesso può ancora
 * avere senso come dato indipendente dal riferimento perduto.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values
                ADD COLUMN block_id INT UNSIGNED NULL AFTER entry_id,
                ADD KEY idx_content_entry_values_block (block_id),
                ADD CONSTRAINT fk_content_entry_values_block
                    FOREIGN KEY (block_id) REFERENCES content_entry_blocks (id) ON DELETE CASCADE"
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values
                DROP FOREIGN KEY fk_content_entry_values_block,
                DROP KEY idx_content_entry_values_block,
                DROP COLUMN block_id"
        );
    }
};
