<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * "Content Type → Template → Theme: più varianti di rendering
 * selezionabili per singola entry" (Template per Content Type).
 *
 * template_options è l'elenco delle varianti dichiarate per il Content
 * Type (es. ["default", "full-width", "case-study"]) — metadato di
 * definizione, non un valore di un'istanza: stesso ruolo di fields.config
 * per le opzioni di una select, stessa ammissibilità del JSON per
 * l'Invariante #3 (non richiede query/filtro/relazione).
 *
 * Nullable: NULL o [] = nessun vincolo dichiarato, content_types.template
 * e content_entries.template restano stringhe libere come oggi
 * (retrocompatibile). Se valorizzato, content_types.template (default) e
 * content_entries.template (override) devono essere uno dei valori
 * elencati — validato in ContentTypeService/ContentEntryService, non a
 * livello DB.
 *
 * La risoluzione effettiva nome-template -> file di vista resta al Theme
 * (Frontend, non ancora costruito) — qui solo dichiarazione e validazione
 * della scelta.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_types
                ADD COLUMN template_options JSON NULL AFTER template"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE content_types DROP COLUMN template_options");
    }
};
