<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Redirect 301 (URL, Slug, Redirect) — schema letterale del documento.
 * source/destination sono path URL completi, comprensivi di eventuale
 * locale/namespace: niente colonna lingua separata. Generati
 * automaticamente al cambio slug (ContentEntryTranslationService).
 *
 * Nessuna FK verso content_entries: sono stringhe di path indipendenti
 * dal ciclo di vita della entry che le ha generate (un redirect deve
 * restare valido anche se l'entry viene poi cancellata).
 *
 * UNIQUE su source: due regole di redirect per lo stesso path sarebbero
 * ambigue su quale destination applicare.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE redirects (
                id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                source      VARCHAR(500)  NOT NULL,
                destination VARCHAR(500)  NOT NULL,
                status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
                active      BOOLEAN       NOT NULL DEFAULT TRUE,
                created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_redirects_source (source),
                KEY idx_redirects_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE redirects");
    }
};
