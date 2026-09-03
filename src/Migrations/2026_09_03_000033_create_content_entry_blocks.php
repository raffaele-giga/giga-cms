<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Istanze di blocco piazzate su un'entry (una Page, tipicamente), in
 * sequenza — un'entry può avere più istanze dello stesso Block Type
 * (es. 3 blocchi "hero"), per questo serve una riga per istanza e non
 * un'assegnazione diretta content_entry <-> block_type.
 *
 * I valori dei campi di ciascuna istanza vivono in content_entry_values
 * tramite la colonna block_id aggiunta lì (migration successiva) — questa
 * tabella è solo "quali istanze esistono, di che tipo, in che ordine",
 * non i loro dati.
 *
 * block_type_id RESTRICT: coerente con lo stile "protetto finché non
 * scollegato esplicitamente" — non si cancella un Block Type ancora
 * usato da istanze reali. entry_id CASCADE: cancellare l'entry rimuove
 * le sue istanze di blocco (e a cascata, tramite content_entry_values,
 * i loro valori).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entry_blocks (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entry_id      INT UNSIGNED NOT NULL,
                block_type_id INT UNSIGNED NOT NULL,
                sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
                created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_content_entry_blocks_entry (entry_id),
                KEY idx_content_entry_blocks_type (block_type_id),
                CONSTRAINT fk_content_entry_blocks_entry
                    FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_entry_blocks_type
                    FOREIGN KEY (block_type_id) REFERENCES block_types (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entry_blocks");
    }
};
