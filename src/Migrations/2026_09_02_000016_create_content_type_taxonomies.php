<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Pivot M:N content_types <-> taxonomies, con ordinamento per Content
 * Type — stesso ruolo di content_type_field_groups, ma per le tassonomie.
 * Una tassonomia è condivisa tra Content Type diversi (Content Engine,
 * "Tassonomie").
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_type_taxonomies (
                content_type_id INT UNSIGNED NOT NULL,
                taxonomy_id     INT UNSIGNED NOT NULL,
                sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (content_type_id, taxonomy_id),
                KEY idx_content_type_taxonomies_taxonomy (taxonomy_id),
                CONSTRAINT fk_content_type_taxonomies_type
                    FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_type_taxonomies_taxonomy
                    FOREIGN KEY (taxonomy_id) REFERENCES taxonomies (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_type_taxonomies");
    }
};
