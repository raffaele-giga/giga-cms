<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Istanza di un Content Type.
 *
 * Niente colonna is_public: la pubblicità è derivata da status.is_public
 * (Invariante #4) combinato con published_at/published_until — niente stato
 * "scheduled" separato, la programmazione è status=published con
 * published_at nel futuro.
 *
 * include_in_archive/indexable sono proprietà concrete ortogonali al
 * lifecycle editoriale, non derivate dallo status.
 *
 * deleted_at è soft delete tecnico (cestino), separato dal lifecycle:
 * status=archived + deleted_at NULL è "archiviato ma presente",
 * deleted_at valorizzato è "nel cestino".
 *
 * slug/title non sono in questa tabella: sono per-lingua e arrivano con
 * content_entry_translations nello step URL/Localization. template invece
 * resta qui perché non dipende dalla lingua.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entries (
                id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
                content_type_id     INT UNSIGNED  NOT NULL,
                status_id           INT UNSIGNED  NOT NULL,
                published_at        DATETIME      NULL,
                published_until     DATETIME      NULL,
                include_in_archive  BOOLEAN       NOT NULL DEFAULT TRUE,
                indexable           BOOLEAN       NOT NULL DEFAULT TRUE,
                sort_order          INT UNSIGNED  NOT NULL DEFAULT 0,
                is_featured         BOOLEAN       NOT NULL DEFAULT FALSE,
                template            VARCHAR(100)  NULL,
                deleted_at          DATETIME      NULL,
                created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_content_entries_type_status (content_type_id, status_id),
                KEY idx_content_entries_published_at (published_at),
                KEY idx_content_entries_deleted_at (deleted_at),
                CONSTRAINT fk_content_entries_content_type
                    FOREIGN KEY (content_type_id) REFERENCES content_types (id) ON DELETE RESTRICT,
                CONSTRAINT fk_content_entries_status
                    FOREIGN KEY (status_id) REFERENCES content_statuses (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entries");
    }
};
