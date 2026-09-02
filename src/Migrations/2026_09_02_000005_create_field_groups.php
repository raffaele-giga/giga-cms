<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Field Group: struttura dati riutilizzabile tra Content Type diversi
 * (Content Engine, sezione "Custom Fields (Field Group) e Field Type").
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE field_groups (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug        VARCHAR(100) NOT NULL,
                label       VARCHAR(150) NOT NULL,
                description VARCHAR(255) NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_field_groups_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE field_groups");
    }
};
