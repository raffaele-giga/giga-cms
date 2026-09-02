<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Pivot M:N field_groups <-> fields, con ordinamento per gruppo.
 *
 * field_group_id CASCADE: cancellare un gruppo rimuove solo l'appartenenza,
 * non i field stessi (restano, eventualmente ancora usati in altri gruppi).
 * field_id RESTRICT: coerente con content_type_field_groups.field_group_id
 * — impedisce di cancellare un field ancora assegnato a un gruppo, forzando
 * uno scollegamento esplicito prima (stessa politica "proteggi finché non
 * viene scollegato", non solo la protezione — diversa — su
 * content_entry_values.field_id per i field già usati da un'entry).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE field_group_fields (
                field_group_id INT UNSIGNED NOT NULL,
                field_id       INT UNSIGNED NOT NULL,
                sort_order     INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (field_group_id, field_id),
                KEY idx_field_group_fields_field (field_id),
                CONSTRAINT fk_field_group_fields_group
                    FOREIGN KEY (field_group_id) REFERENCES field_groups (id) ON DELETE CASCADE,
                CONSTRAINT fk_field_group_fields_field
                    FOREIGN KEY (field_id) REFERENCES fields (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE field_group_fields");
    }
};
