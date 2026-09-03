<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * Asse follow/nofollow del robots meta (SEO: "index/follow, noindex/follow,
 * noindex/nofollow"). L'asse index/noindex è già coperto dalla colonna
 * indexable esistente (Content Entry, "proprietà concrete e ortogonali al
 * lifecycle") — qui manca solo il secondo asse. Entry-level come
 * indexable, non per-lingua: stessa scelta già fatta per quella colonna,
 * mantenuta per coerenza invece di introdurre un'asimmetria.
 *
 * Default TRUE: un'entry appena creata è "index,follow" salvo scelta
 * esplicita contraria, coerente con indexable che di default è TRUE.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entries
                ADD COLUMN follow BOOLEAN NOT NULL DEFAULT TRUE AFTER indexable"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE content_entries DROP COLUMN follow");
    }
};
