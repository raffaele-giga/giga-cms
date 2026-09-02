<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Pattern di permalink per Content Type (URL, Slug, Redirect): "pattern
 * configurabile per Content Type e per lingua". L'override per lingua non
 * ha ancora una tabella dedicata — il documento lo dà esplicitamente per
 * non necessario ora ("non serve definire la tabella ora, ma il modello
 * deve prevederlo"): questa colonna è il pattern di default a livello di
 * Content Type, l'eventuale content_type_translations per l'override
 * resta un'estensione futura additiva, non preclusa da questa scelta.
 *
 * Nullable: se non impostato, il Service usa un fallback calcolato
 * (/{content_type.slug}/{slug}) invece di un default hardcoded qui.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_types
                ADD COLUMN permalink_pattern VARCHAR(255) NULL AFTER default_ordering"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE content_types DROP COLUMN permalink_pattern");
    }
};
