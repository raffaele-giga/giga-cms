<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * FK differita dalla migration di content_entry_values (2026_09_02_000004):
 * a quel punto `media` non esisteva ancora.
 *
 * SET NULL, non RESTRICT: value_media_id è il valore di un field su una
 * entry di contenuto (es. "Immagine in evidenza"), non una struttura di
 * associazione — stessa policy già scelta per value_entry_id. Forzare la
 * rimozione manuale da ogni entry che referenzia un asset prima di poterlo
 * cancellare dalla libreria non scalerebbe (un logo riusato in 200 entry
 * bloccherebbe la sua stessa cancellazione). La gallery (content_entry_media)
 * ha invece FK RESTRICT su media_id: lì la relazione è strutturale, non un
 * singolo valore di contenuto.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values
                ADD CONSTRAINT fk_content_entry_values_value_media
                    FOREIGN KEY (value_media_id) REFERENCES media (id) ON DELETE SET NULL"
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            "ALTER TABLE content_entry_values DROP FOREIGN KEY fk_content_entry_values_value_media"
        );
    }
};
