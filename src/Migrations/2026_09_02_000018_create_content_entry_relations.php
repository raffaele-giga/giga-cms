<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Relazioni tra entry (Content Engine, "Relazioni"; Invariante #8):
 * direzionali per default, la relazione inversa è sempre una query su
 * questa stessa tabella (related_entry_id = ?), mai una seconda riga da
 * sincronizzare — vedi ContentEntryRepository::getInverseRelations().
 *
 * relation_type non è una FK verso fields: è una stringa libera che
 * identifica il tipo di relazione (es. 'related_projects'), condivisa
 * potenzialmente tra Content Type diversi — stessa scelta già fatta per
 * content_entry_terms (niente field_id), il sistema supporta 1:N/N:N
 * "mediante la configurazione del field/relation type" a livello
 * applicativo, non attraverso una FK rigida qui.
 *
 * sort_order non è nello schema letterale del documento (solo entry_id,
 * related_entry_id, relation_type) ma è un'estensione ragionevole,
 * coerente con content_entry_media: un editor può voler ordinare
 * manualmente una lista di "progetti correlati".
 *
 * entry_id e related_entry_id sono entrambi CASCADE: a differenza di
 * content_entry_media/content_entry_terms (dove il target — media/term —
 * è una risorsa riusabile indipendente), qui il "target" è un'altra entry
 * di contenuto, e la riga di relazione non ha alcun valore autonomo se
 * una delle due entry sparisce — stesso trattamento già riservato a
 * entry_id su content_entry_values/content_entry_media/content_entry_terms.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entry_relations (
                id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
                entry_id         INT UNSIGNED NOT NULL,
                related_entry_id INT UNSIGNED NOT NULL,
                relation_type    VARCHAR(100) NOT NULL,
                sort_order       INT UNSIGNED NOT NULL DEFAULT 0,
                created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_content_entry_relations (entry_id, related_entry_id, relation_type),
                KEY idx_content_entry_relations_inverse (related_entry_id, relation_type),
                CONSTRAINT fk_content_entry_relations_entry
                    FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_entry_relations_related
                    FOREIGN KEY (related_entry_id) REFERENCES content_entries (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entry_relations");
    }
};
