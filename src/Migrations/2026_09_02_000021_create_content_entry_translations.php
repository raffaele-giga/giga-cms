<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Campi core traducibili di un'entry (Modello multilingua): title/slug
 * sono per-lingua per costruzione (/it/realizzazioni/mio-progetto vs
 * /en/case-studies/my-project sono slug diversi, non lo stesso valore con
 * prefisso lingua) — vedi la nota su content_entries (slug/title non sono
 * proprietà dirette dell'entry, vivono qui).
 *
 * content_type_id è denormalizzato da entry_id->content_entries.content_type_id:
 * serve per l'UNIQUE sullo slug scoped al Content Type (collision
 * strategy — due Content Type diversi possono avere entrambi uno slug
 * "hello-world" senza collisione reale, dato che il permalink pattern
 * include già il prefisso specifico del tipo).
 *
 * UNIQUE (entry_id, language_id): una sola traduzione per lingua per
 * entry. entry_id CASCADE (le traduzioni non hanno senso senza l'entry).
 * content_type_id/language_id RESTRICT: coerenti con lo stile "protetto
 * finché non scollegato esplicitamente" già in uso nello schema.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entry_translations (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entry_id        INT UNSIGNED NOT NULL,
                content_type_id INT UNSIGNED NOT NULL,
                language_id     INT UNSIGNED NOT NULL,
                title           VARCHAR(255) NOT NULL,
                slug            VARCHAR(255) NOT NULL,
                created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_content_entry_translations_entry_lang (entry_id, language_id),
                UNIQUE KEY uq_content_entry_translations_slug (content_type_id, language_id, slug),
                KEY idx_content_entry_translations_language (language_id),
                CONSTRAINT fk_content_entry_translations_entry
                    FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_entry_translations_type
                    FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE RESTRICT,
                CONSTRAINT fk_content_entry_translations_language
                    FOREIGN KEY (language_id) REFERENCES languages (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entry_translations");
    }
};
