<?php

use Giga\Core\Database;
use Giga\Core\Migrations\Migration;

/**
 * "Ogni item può puntare a: pagina interna, Content Entry, URL esterno,
 * anchor" (Navigation/Menu). "Pagina interna" e "Content Entry" sono la
 * stessa cosa a livello di schema: una Page è semplicemente un'entry del
 * Content Type di sistema "Page" — non c'è una distinzione da modellare.
 *
 * target_variant è lo stesso discriminatore già usato per LinkFieldType
 * (value_variant): il target cambia colonna in base al kind ('entry' ->
 * target_entry_id, 'url'/'anchor' -> target_url, un anchor è solo un
 * frammento come "#sezione", non serve una colonna dedicata).
 *
 * parent_id RESTRICT: stesso pattern anti-eliminazione-con-figli di
 * taxonomy_terms.parent_id. target_entry_id SET NULL: stesso pattern di
 * value_entry_id — un link a un'entry cancellata diventa un item rotto da
 * correggere, non blocca la cancellazione dell'entry altrove nel sistema.
 */
return new class extends Migration {
    public function up(Database $db): void
    {
        $db->execute(
            "CREATE TABLE menu_items (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                menu_id         INT UNSIGNED NOT NULL,
                parent_id       INT UNSIGNED NULL,
                label           VARCHAR(150) NOT NULL,
                target_variant  VARCHAR(20)  NOT NULL,
                target_entry_id INT UNSIGNED NULL,
                target_url      VARCHAR(500) NULL,
                sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
                created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_menu_items_menu (menu_id),
                KEY idx_menu_items_parent (parent_id),
                KEY idx_menu_items_target_entry (target_entry_id),
                CONSTRAINT fk_menu_items_menu
                    FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE,
                CONSTRAINT fk_menu_items_parent
                    FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE RESTRICT,
                CONSTRAINT fk_menu_items_target_entry
                    FOREIGN KEY (target_entry_id) REFERENCES content_entries (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(Database $db): void
    {
        $db->execute("DROP TABLE menu_items");
    }
};
