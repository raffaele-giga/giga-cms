<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Lingue attive dell'installazione (Modello multilingua). Un solo record
 * può avere is_default=true — vincolo di business (LanguageService), non
 * un constraint DB: MySQL non esprime facilmente "al più una riga vera".
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE languages (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code       VARCHAR(10)  NOT NULL,
                name       VARCHAR(100) NOT NULL,
                locale     VARCHAR(20)  NOT NULL,
                is_default BOOLEAN      NOT NULL DEFAULT FALSE,
                active     BOOLEAN      NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_languages_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE languages");
    }
};
