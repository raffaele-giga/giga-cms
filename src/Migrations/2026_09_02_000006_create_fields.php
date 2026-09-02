<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Field: definizione di un Custom Field, standalone e riusabile in più
 * Field Group tramite field_group_fields (pivot M:N) — non appartiene a
 * un solo gruppo.
 *
 * type è una stringa libera, validata a livello applicativo dal Field Type
 * registry (PHP), non da un vincolo DB: "il database non conosce la logica
 * specifica del Field Type" (Content Engine, sezione Custom Fields) — così
 * aggiungere un tipo futuro (ColorField, MapField...) non richiede una
 * migration.
 *
 * config è JSON per configurazione specifica del field (es. opzioni di una
 * select, min/max di un numero) — non tassonomie/relazioni/gallery, che
 * restano sempre relazionali (Invariante #3): questo è metadato di
 * definizione del campo, non un valore di un'istanza, quindi non in
 * conflitto con la regola su content_entry_values.value_json.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE fields (
                id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `key`        VARCHAR(100) NOT NULL,
                label        VARCHAR(150) NOT NULL,
                type         VARCHAR(50)  NOT NULL,
                translatable BOOLEAN      NOT NULL DEFAULT FALSE,
                required     BOOLEAN      NOT NULL DEFAULT FALSE,
                config       JSON         NULL,
                created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_fields_key (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE fields");
    }
};
