<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * FK differita dalla migration di content_entry_values (2026_09_02_000004):
 * a quel punto `fields` non esisteva ancora. RESTRICT — coerente con
 * Invariante #9 (nessuna perdita dati senza strategia esplicita): non si
 * può cancellare un field ancora referenziato da un valore di un'entry.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values
                ADD CONSTRAINT fk_content_entry_values_field
                    FOREIGN KEY (field_id) REFERENCES fields (id) ON DELETE RESTRICT"
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values DROP FOREIGN KEY fk_content_entry_values_field"
        );
    }
};
