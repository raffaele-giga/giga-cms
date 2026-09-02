<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Termini di una tassonomia (es. "Manufacturing"/"Creative" per Industry).
 * slug univoco per tassonomia, non globalmente: due tassonomie diverse
 * possono avere entrambe un termine "other" senza collisione.
 *
 * parent_id abilita la gerarchia solo se taxonomies.is_hierarchical=true
 * (non imposto qui a livello DB, verificato dal Service). RESTRICT su
 * entrambe le FK: stessa politica "protetto finché non scollegato
 * esplicitamente" già in uso nello schema — cancellare una tassonomia con
 * termini, o un termine con figli, richiede prima di svuotarli
 * esplicitamente (Invariante #9).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE taxonomy_terms (
                id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                taxonomy_id  INT UNSIGNED NOT NULL,
                parent_id    INT UNSIGNED NULL,
                slug         VARCHAR(100) NOT NULL,
                label        VARCHAR(150) NOT NULL,
                sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
                created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_taxonomy_terms_slug (taxonomy_id, slug),
                KEY idx_taxonomy_terms_parent (parent_id),
                CONSTRAINT fk_taxonomy_terms_taxonomy
                    FOREIGN KEY (taxonomy_id) REFERENCES taxonomies (id) ON DELETE RESTRICT,
                CONSTRAINT fk_taxonomy_terms_parent
                    FOREIGN KEY (parent_id) REFERENCES taxonomy_terms (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE taxonomy_terms");
    }
};
