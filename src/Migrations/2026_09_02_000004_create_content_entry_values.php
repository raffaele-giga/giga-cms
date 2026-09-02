<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Persistenza dei Custom Field scalari (Invariante #2: mai una tabella
 * generata per Content Type) con colonne tipizzate per valore
 * (Decisione #1). value_json ammesso solo per strutture composite
 * realmente non relazionali — mai per tassonomie, relazioni a cardinalità
 * multipla o gallery (Invariante #3).
 *
 * value_media_id / value_entry_id coprono solo cardinalità 1. Relazioni
 * 1:N/N:N useranno content_entry_relations, gallery/collezioni
 * content_entry_media — entrambe fuori scope in questo step.
 *
 * sort_order supporta i field di tipo repeater (più righe per la stessa
 * coppia entry_id/field_id).
 *
 * Nessuna FK su field_id: la tabella `fields` arriva con Field
 * Groups/Fields (roadmap "Content Type Project end-to-end"), la constraint
 * sarà aggiunta con ALTER TABLE a quel punto, non retrofittata qui.
 * Stesso motivo per l'assenza di FK su value_media_id (Asset Library,
 * step Media/Gallery). value_entry_id invece referenzia content_entries,
 * già esistente in questo stesso step.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entry_values (
                id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                entry_id       INT UNSIGNED  NOT NULL,
                field_id       INT UNSIGNED  NOT NULL,
                sort_order     INT UNSIGNED  NOT NULL DEFAULT 0,
                value_text     TEXT          NULL,
                value_number   DECIMAL(20,6) NULL,
                value_boolean  BOOLEAN       NULL,
                value_date     DATE          NULL,
                value_datetime DATETIME      NULL,
                value_media_id INT UNSIGNED  NULL,
                value_entry_id INT UNSIGNED  NULL,
                value_json     JSON          NULL,
                created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_content_entry_values_entry_field (entry_id, field_id),
                KEY idx_content_entry_values_value_entry (value_entry_id),
                KEY idx_content_entry_values_value_media (value_media_id),
                CONSTRAINT fk_content_entry_values_entry
                    FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_entry_values_value_entry
                    FOREIGN KEY (value_entry_id) REFERENCES content_entries (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entry_values");
    }
};
