<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/** Navigation/Menu (V1): un'installazione può avere più menu (main-nav, footer, ...). */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE menus (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                slug       VARCHAR(100) NOT NULL,
                label      VARCHAR(150) NOT NULL,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_menus_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE menus");
    }
};
