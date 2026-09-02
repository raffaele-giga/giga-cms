<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Override del permalink pattern per la coppia (Content Type, Language) —
 * Decisione #2: "il permalink pattern è definito per Content Type e può
 * essere sovrascritto per lingua". PRIMARY KEY composita (niente id
 * surrogato): è un override 1:1 per coppia, stesso stile delle pivot pure
 * già in uso (field_group_fields, content_type_field_groups), non un
 * aggregato con dati propri oltre al pattern stesso.
 *
 * Nessuna migrazione dati necessaria: senza una riga qui,
 * ContentEntryTranslationService::buildPermalink() continua a risolvere
 * sul fallback già esistente (content_types.permalink_pattern, poi
 * /{content_type.slug}/{slug}) — additiva, non cambia nulla per chi non
 * la usa.
 *
 * content_type_id CASCADE: cancellare il Content Type rimuove anche i
 * suoi override (pura configurazione, nessuna perdita di dati di
 * contenuto). language_id RESTRICT: coerente con l'uso di language_id
 * altrove nello schema — non si cancella una lingua ancora referenziata.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_type_permalink_patterns (
                content_type_id INT UNSIGNED NOT NULL,
                language_id     INT UNSIGNED NOT NULL,
                pattern         VARCHAR(255) NOT NULL,
                created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (content_type_id, language_id),
                KEY idx_content_type_permalink_patterns_language (language_id),
                CONSTRAINT fk_content_type_permalink_patterns_type
                    FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_type_permalink_patterns_language
                    FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_type_permalink_patterns");
    }
};
