<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Assegnazione dei termini alle entry — pivot M:N, non mediata dal sistema
 * Field/Field Group (nessun field_id): il Contratto Theme↔CMS accede alle
 * tassonomie per slug direttamente sull'entry ($project->taxonomy('industry')),
 * non come un Custom Field. Un'entry può avere termini di più tassonomie
 * contemporaneamente; ContentEntryService::syncTerms() scoping per
 * taxonomy_id tiene le assegnazioni di tassonomie diverse indipendenti,
 * stesso principio di replaceGallery() scoped per field_id.
 *
 * term_id RESTRICT: coerente con content_entry_media.media_id — relazione
 * strutturale (assegnazione), non un valore di contenuto, quindi niente
 * SET NULL.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entry_terms (
                entry_id   INT UNSIGNED NOT NULL,
                term_id    INT UNSIGNED NOT NULL,
                created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (entry_id, term_id),
                KEY idx_content_entry_terms_term (term_id),
                CONSTRAINT fk_content_entry_terms_entry
                    FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE,
                CONSTRAINT fk_content_entry_terms_term
                    FOREIGN KEY (term_id) REFERENCES taxonomy_terms (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entry_terms");
    }
};
