<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Statistiche di lettura pubblica per Content Entry (view_count,
 * last_viewed_at) — tabella completamente separata da content_entries:
 * nessuna interazione con LockService/audit, che restano legati solo al
 * path di scrittura editoriale. entry_id è PRIMARY KEY (una riga per
 * entry, mai più di una) invece di un id surrogato, come le altre
 * tabelle chiave-valore/estensione 1:1 di questo progetto.
 *
 * FK CASCADE: cancellare l'entry rimuove le sue statistiche, non ha
 * senso lasciarle orfane. Il flag che decide se un Content Type
 * accumula queste statistiche vive su content_types (supports_stats,
 * migration successiva) — questa tabella resta agnostica, riceve righe
 * solo per le entry i cui Content Type lo abilitano.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE content_entry_stats (
                entry_id       INT UNSIGNED NOT NULL,
                view_count     INT UNSIGNED NOT NULL DEFAULT 0,
                last_viewed_at DATETIME     NULL,
                PRIMARY KEY (entry_id),
                CONSTRAINT fk_content_entry_stats_entry
                    FOREIGN KEY (entry_id) REFERENCES content_entries (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE content_entry_stats");
    }
};
