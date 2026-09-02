<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Lista configurabile degli stati editoriali (Invariante #6: mai un ENUM
 * hardcoded). content_entries.status_id referenzia questa tabella.
 *
 * is_public è l'unica fonte di verità per la pubblicità di un'entry
 * (Invariante #4) — is_terminal segnala una condizione editoriale conclusiva
 * senza implicare pubblicazione o cancellazione (es. archived è terminale
 * ma non pubblico, published è pubblico ma non terminale).
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_statuses (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                system_key  VARCHAR(50)  NOT NULL,
                label       VARCHAR(100) NOT NULL,
                is_system   BOOLEAN      NOT NULL DEFAULT FALSE,
                is_public   BOOLEAN      NOT NULL DEFAULT FALSE,
                is_terminal BOOLEAN      NOT NULL DEFAULT FALSE,
                created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_content_statuses_system_key (system_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->execute(
            "INSERT INTO content_statuses (system_key, label, is_system, is_public, is_terminal) VALUES
                ('draft',     'Draft',     TRUE, FALSE, FALSE),
                ('published', 'Published', TRUE, TRUE,  FALSE),
                ('archived',  'Archived',  TRUE, FALSE, TRUE)"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_statuses");
    }
};
