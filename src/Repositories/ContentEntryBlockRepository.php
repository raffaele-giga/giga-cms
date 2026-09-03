<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\ContentEntryBlock;

/**
 * A differenza di gallery/termini/relazioni (pure associazioni, gestite
 * con delete+insert via replaceX()), un'istanza di blocco porta con sé
 * dati propri (i suoi content_entry_values scoped per block_id) — un
 * delete+insert la distruggerebbe e la ricreerebbe con un id diverso a
 * ogni riordino, perdendo tutti i suoi valori (CASCADE su block_id).
 * Serve identità stabile: create/delete puntuali, reorder che aggiorna
 * sort_order sulle righe esistenti, mai un "replace" totale.
 */
class ContentEntryBlockRepository
{
    private Database $db;
    private ContentEntryBlock $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new ContentEntryBlock();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    /** Istanze di blocco di un'entry, ordinate, con lo slug/label del Block Type. */
    public function findByEntry(int $entryId): array
    {
        return $this->db->fetchAll(
            "SELECT ceb.*, bt.slug AS block_type_slug, bt.label AS block_type_label
             FROM content_entry_blocks ceb
             JOIN block_types bt ON bt.id = ceb.block_type_id
             WHERE ceb.entry_id = ?
             ORDER BY ceb.sort_order ASC",
            [$entryId]
        );
    }

    public function create(array $data): int
    {
        return $this->model->insert($this->filterFields($data));
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM content_entry_blocks WHERE id = ?", [$id]) > 0;
    }

    /** Aggiorna sort_order sulle righe esistenti in base alla posizione in $orderedBlockIds — mai delete+insert. */
    public function reorder(int $entryId, array $orderedBlockIds): void
    {
        $this->db->transaction(function () use ($entryId, $orderedBlockIds) {
            foreach (array_values($orderedBlockIds) as $sortOrder => $blockId) {
                $this->db->execute(
                    "UPDATE content_entry_blocks SET sort_order = ? WHERE id = ? AND entry_id = ?",
                    [$sortOrder, $blockId, $entryId]
                );
            }
        });
    }

    private function filterFields(array $data): array
    {
        $allowed = ['entry_id', 'block_type_id', 'sort_order'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
