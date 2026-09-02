<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Definizione/schema di un Content Type (system o custom).
 *
 * is_system distingue i Content Type forniti da giga-cms (es. Page) da
 * quelli creati dinamicamente dall'admin — i primi possono avere
 * regole/capacità non disponibili ai secondi (Content Engine, sezione
 * "Content Type vs Content Entry").
 *
 * default_ordering: 'published_at' | 'created_at' | 'alphabetical' | 'manual' | 'custom_field'
 * (il campo custom per l'ordinamento arriverà con la tabella `fields`).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_types (
                id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug                VARCHAR(100)  NOT NULL,
                label               VARCHAR(150)  NOT NULL,
                label_singular      VARCHAR(150)  NOT NULL,
                is_system           BOOLEAN       NOT NULL DEFAULT FALSE,
                supports_archive    BOOLEAN       NOT NULL DEFAULT FALSE,
                supports_seo        BOOLEAN       NOT NULL DEFAULT FALSE,
                supports_media      BOOLEAN       NOT NULL DEFAULT FALSE,
                supports_featured   BOOLEAN       NOT NULL DEFAULT FALSE,
                default_ordering    VARCHAR(20)   NOT NULL DEFAULT 'created_at',
                template            VARCHAR(100)  NULL,
                include_in_sitemap  BOOLEAN       NOT NULL DEFAULT TRUE,
                admin_icon          VARCHAR(50)   NULL,
                admin_menu          VARCHAR(100)  NULL,
                admin_order         INT UNSIGNED  NOT NULL DEFAULT 0,
                admin_enabled       BOOLEAN       NOT NULL DEFAULT TRUE,
                created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_content_types_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_types");
    }
};
