<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * "Block Type è un'entità di presentazione distinta che usa il motore
 * Field Group per il proprio schema dati, ma aggiunge identificatore,
 * validazione e template" (Page Builder — Block Type vs Field Group).
 * slug è l'identificatore (es. 'hero') che il frontend risolve a
 * template (themes/current/blocks/hero.php) — quella risoluzione resta
 * al Theme, non a questa tabella.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE block_types (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug       VARCHAR(100) NOT NULL,
                label      VARCHAR(150) NOT NULL,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_block_types_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE block_types");
    }
};
