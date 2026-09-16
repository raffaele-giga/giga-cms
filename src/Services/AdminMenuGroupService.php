<?php

namespace Giga\Cms\Services;

use Giga\Core\Database;

/**
 * Gruppi di menu admin (sezioni della sidebar generate dai Content Type) —
 * Service dedicato, non metodi aggiunti a ContentTypeService: gestisce una
 * tabella/concetto proprio (admin_menu_groups), indipendente dal ciclo di
 * vita di un Content Type — un gruppo può esistere, essere riordinato o
 * restare vuoto indipendentemente da quali Content Type lo referenzino in
 * quel momento (content_types.admin_menu è una stringa libera, non una FK
 * verso questa tabella).
 *
 * Permission-agnostic per design, stesso principio di ContentTypeService/
 * ContentEntryService — vedi i rispettivi docblock.
 */
class AdminMenuGroupService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array[] tutti i gruppi, ordinati per sort_order */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM admin_menu_groups ORDER BY sort_order ASC, label ASC"
        );
    }

    /**
     * Crea il gruppo se non esiste già una riga con quel label — idempotente,
     * safe da richiamare ad ogni submit del form Content Type anche se il
     * gruppo esiste già. sort_order del nuovo gruppo = MAX(sort_order) + 10,
     * non +1: gap deliberato, lascia spazio per riordini futuri (inserire un
     * gruppo tra due esistenti) senza dover rinumerare tutti gli altri.
     */
    public function ensureExists(string $label): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        $existing = $this->db->fetchOne(
            "SELECT id FROM admin_menu_groups WHERE label = ?",
            [$label]
        );
        if ($existing) {
            return;
        }

        $row = $this->db->fetchOne("SELECT MAX(sort_order) AS max_order FROM admin_menu_groups");
        $nextOrder = (int) ($row['max_order'] ?? 0) + 10;

        $this->db->execute(
            "INSERT INTO admin_menu_groups (label, sort_order) VALUES (?, ?)",
            [$label, $nextOrder]
        );
    }

    public function updateOrder(int $id, int $sortOrder): void
    {
        $this->db->execute(
            "UPDATE admin_menu_groups SET sort_order = ? WHERE id = ?",
            [$sortOrder, $id]
        );
    }
}
