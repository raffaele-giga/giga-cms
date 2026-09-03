<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * "giga-cms.installation_settings → configurazione editoriale/site-specific:
 * nome sito, logo, tema, lingue, homepage, moduli CMS attivi, terminologia,
 * SEO defaults" — confine esplicito rispetto a giga-core.app_settings
 * (infrastrutturale/applicativo).
 *
 * Stesso identico schema chiave-valore di app_settings (verificato sul
 * codice reale di wos-pro/cms-neviobianchi: `key` VARCHAR(64) PK, `value`
 * TEXT nullable, updated_at) — nessuno schema nuovo da inventare, stesso
 * meccanismo, dominio diverso. "Lingue" non ha una propria chiave qui:
 * ha già una tabella relazionale dedicata (languages), non duplicata come
 * stringa in un blob di settings.
 *
 * Seed dei default noti, stesso stile della riga INSERT INTO app_settings
 * nella migration base di wos-pro/cms-neviobianchi.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE installation_settings (
                `key`      VARCHAR(64) NOT NULL,
                value      TEXT        NULL,
                updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->execute(
            "INSERT INTO installation_settings (`key`, value) VALUES
                ('theme', 'default'),
                ('page_builder_enabled', '1'),
                ('content_type_authoring_enabled', '0'),
                ('terminology', '{}')"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE installation_settings");
    }
};
