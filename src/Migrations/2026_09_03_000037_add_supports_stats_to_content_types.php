<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Capability opzionale, stesso pattern di supports_archive/supports_seo/
 * supports_media/supports_featured: non ogni Content Type ha bisogno di
 * statistiche di lettura (es. "Team" tipicamente no). Quando è FALSE,
 * Cms::recordView() non scrive mai una riga in content_entry_stats per
 * le entry di quel tipo — nessun accumulo di dati non voluto.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_types
                ADD COLUMN supports_stats BOOLEAN NOT NULL DEFAULT FALSE AFTER supports_featured"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE content_types DROP COLUMN supports_stats");
    }
};
