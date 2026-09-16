<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Gruppi di menu admin per i Content Type — sezioni della sidebar generate
 * dinamicamente da ContentTypeService::getAllForAdminMenu() (che filtra già
 * admin_enabled=1, ordina per admin_order). content_types.admin_menu resta
 * una stringa libera (non FK verso questa tabella): questa tabella esiste
 * solo per dare un sort_order esplicito, editabile, alle SEZIONI risultanti
 * dal raggruppamento per quella stringa — non per vincolare/validare il
 * valore di admin_menu stesso.
 *
 * label UNIQUE: un gruppo è identificato dalla stringa che i Content Type
 * scrivono in admin_menu, non da uno slug separato — AdminMenuGroupService::
 * ensureExists() fa lookup per label esatta.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE admin_menu_groups (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                label      VARCHAR(100) NOT NULL,
                sort_order INT          NOT NULL DEFAULT 0,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_admin_menu_groups_label (label)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE admin_menu_groups");
    }
};
