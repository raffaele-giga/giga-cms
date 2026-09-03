<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Meta SEO per-lingua (SEO, layer trasversale): meta title/description e
 * canonical sono traducibili per costruzione, come title/slug — vivono
 * qui, non su content_entries (Modello multilingua).
 *
 * Tutte nullable: NULL = usa il fallback calcolato da
 * ContentEntryTranslationService::getSeoMeta() (meta_title -> title,
 * canonical_url -> permalink calcolato, og_title/og_description ->
 * meta_title/meta_description), non un valore vuoto forzato.
 *
 * og_media_id: stessa FK/policy di content_entry_values.value_media_id
 * (SET NULL — è il valore di un campo su una entry di contenuto, non una
 * struttura da proteggere con RESTRICT).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_translations
                ADD COLUMN meta_title       VARCHAR(255) NULL AFTER slug,
                ADD COLUMN meta_description VARCHAR(500) NULL AFTER meta_title,
                ADD COLUMN canonical_url    VARCHAR(500) NULL AFTER meta_description,
                ADD COLUMN og_title         VARCHAR(255) NULL AFTER canonical_url,
                ADD COLUMN og_description   VARCHAR(500) NULL AFTER og_title,
                ADD COLUMN og_media_id      INT UNSIGNED NULL AFTER og_description,
                ADD KEY idx_content_entry_translations_og_media (og_media_id),
                ADD CONSTRAINT fk_content_entry_translations_og_media
                    FOREIGN KEY (og_media_id) REFERENCES media (id) ON DELETE SET NULL"
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_translations
                DROP FOREIGN KEY fk_content_entry_translations_og_media,
                DROP KEY idx_content_entry_translations_og_media,
                DROP COLUMN meta_title,
                DROP COLUMN meta_description,
                DROP COLUMN canonical_url,
                DROP COLUMN og_title,
                DROP COLUMN og_description,
                DROP COLUMN og_media_id"
        );
    }
};
