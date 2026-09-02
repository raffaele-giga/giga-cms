<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Gallery/collezioni di media a cardinalità multipla (Decisione #1) — mai
 * value_json: associazione relazionale dedicata che permette riordino,
 * rimozione di un singolo elemento, riuso del media altrove, caption
 * per elemento.
 *
 * UNIQUE su (entry_id, field_id, media_id): stesso media non due volte
 * nella stessa gallery dello stesso field. id surrogato (non PK composita
 * come le pivot field_group_fields/content_type_field_groups) perché qui
 * ogni riga porta dati propri (sort_order, caption), più simile a
 * content_entry_values che a una pivot pura.
 *
 * field_id/media_id RESTRICT: stessa politica "protetta finché non
 * scollegata esplicitamente" già in uso su content_entry_values.field_id
 * e sulle pivot dei Field Group — qui riguarda una struttura (la gallery),
 * non un singolo valore di contenuto, quindi niente SET NULL.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entry_media (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entry_id    INT UNSIGNED NOT NULL,
                field_id    INT UNSIGNED NOT NULL,
                media_id    INT UNSIGNED NOT NULL,
                sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
                caption     VARCHAR(500) NULL,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_content_entry_media_slot (entry_id, field_id, media_id),
                KEY idx_content_entry_media_field (field_id),
                KEY idx_content_entry_media_media (media_id),
                CONSTRAINT fk_content_entry_media_entry
                    FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_entry_media_field
                    FOREIGN KEY (field_id) REFERENCES fields (id) ON DELETE RESTRICT,
                CONSTRAINT fk_content_entry_media_media
                    FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entry_media");
    }
};
