<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * "Block Type... usa il motore Field Group per il proprio schema dati"
 * (Page Builder — Block Type vs Field Group): stesso ruolo/pattern di
 * content_type_field_groups, ma per i Block Type. Pivot M:N — un Field
 * Group riutilizzabile può fornire lo schema dati a più Block Type.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE block_type_field_groups (
                block_type_id  INT UNSIGNED NOT NULL,
                field_group_id INT UNSIGNED NOT NULL,
                sort_order     INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (block_type_id, field_group_id),
                KEY idx_block_type_field_groups_group (field_group_id),
                CONSTRAINT fk_block_type_field_groups_type
                    FOREIGN KEY (block_type_id) REFERENCES block_types (id) ON DELETE CASCADE,
                CONSTRAINT fk_block_type_field_groups_group
                    FOREIGN KEY (field_group_id) REFERENCES field_groups (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE block_type_field_groups");
    }
};
