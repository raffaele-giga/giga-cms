<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Discriminatore generico per i Field Type la cui colonna valorizzata
 * cambia in base a un "kind" scelto per-valore (es. LinkFieldType: entry
 * vs external vs email...). Senza questa colonna, il solo modo per
 * distinguere i kind sarebbe stato un blob JSON — esattamente ciò che
 * l'Invariante #3 vieta per un riferimento a un'altra entry o a un media,
 * che deve restare sotto protezione FK (value_entry_id/value_media_id),
 * non dentro un JSON.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values
                ADD COLUMN value_variant VARCHAR(20) NULL AFTER sort_order"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE content_entry_values DROP COLUMN value_variant");
    }
};
