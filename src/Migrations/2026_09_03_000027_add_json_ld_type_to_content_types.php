<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * "JSON-LD non hardcoded: il Content Type/Template dichiara lo schema
 * strutturato da usare" (SEO). Questa colonna è la dichiarazione (es.
 * 'Article', 'Product', 'Organization' — un tipo schema.org) — la
 * generazione effettiva del markup JSON-LD a partire dai dati dell'entry
 * resta al Template/Theme, non ancora costruito.
 *
 * Nullable e stringa libera: nessun registro di tipi schema.org validi
 * esiste né è richiesto qui, stesso approccio già usato per fields.type
 * prima del Field Type registry.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_types
                ADD COLUMN json_ld_type VARCHAR(50) NULL AFTER permalink_pattern"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE content_types DROP COLUMN json_ld_type");
    }
};
