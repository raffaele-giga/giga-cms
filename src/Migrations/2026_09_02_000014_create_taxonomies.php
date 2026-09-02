<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Definizione di una tassonomia (es. "Industry", "Tags") — condivisa tra
 * Content Type diversi (Content Engine, "Tassonomie"). is_hierarchical
 * distingue flat (tag) da gerarchiche (categorie con parent/child, vedi
 * taxonomy_terms.parent_id).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE taxonomies (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug            VARCHAR(100)  NOT NULL,
                label           VARCHAR(150)  NOT NULL,
                label_singular  VARCHAR(150)  NOT NULL,
                is_hierarchical BOOLEAN       NOT NULL DEFAULT FALSE,
                created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_taxonomies_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE taxonomies");
    }
};
