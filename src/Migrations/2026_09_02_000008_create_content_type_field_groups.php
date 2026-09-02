<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Pivot M:N content_types <-> field_groups, con ordinamento per Content
 * Type. Un Content Type non ha necessariamente campi propri: è composto
 * da Field Group riutilizzabili (Content Engine, sezione "Content Type").
 *
 * field_group_id RESTRICT: coerente con lo stile già in uso su
 * content_entries (content_type_id/status_id RESTRICT) — impedisce di
 * cancellare un Field Group ancora assegnato a un Content Type, forzando
 * uno scollegamento esplicito prima.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_type_field_groups (
                content_type_id INT UNSIGNED NOT NULL,
                field_group_id  INT UNSIGNED NOT NULL,
                sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (content_type_id, field_group_id),
                KEY idx_content_type_field_groups_group (field_group_id),
                CONSTRAINT fk_content_type_field_groups_type
                    FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_type_field_groups_group
                    FOREIGN KEY (field_group_id) REFERENCES field_groups (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_type_field_groups");
    }
};
