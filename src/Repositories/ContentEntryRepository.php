<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\ContentEntry;
use Giga\Cms\Models\ContentEntryValue;

/**
 * CRUD di content_entries + content_entry_values. Le due tabelle sono
 * gestite insieme perché i values non hanno significato senza la loro
 * entry (aggregato, non due risorse indipendenti) — vedi Decisione #1
 * (persistenza Custom Field) nell'architettura.
 *
 * Repository puro: nessuna regola di business (status di default,
 * validazione permessi, resolve di slug/content_type). Quello è compito
 * del Service — qui si accettano solo ID già risolti.
 */
class ContentEntryRepository
{
    private Database $db;
    private ContentEntry $model;
    private ContentEntryValue $valueModel;

    public function __construct()
    {
        $this->db         = Database::getInstance();
        $this->model      = new ContentEntry();
        $this->valueModel = new ContentEntryValue();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT e.*,
                    ct.slug AS content_type_slug, ct.label_singular AS content_type_label,
                    cs.system_key AS status_key, cs.label AS status_label,
                    cs.is_public AS status_is_public, cs.is_terminal AS status_is_terminal
             FROM content_entries e
             JOIN content_types ct ON ct.id = e.content_type_id
             JOIN content_statuses cs ON cs.id = e.status_id
             WHERE e.id = ? AND e.deleted_at IS NULL
             LIMIT 1",
            [$id]
        );
    }

    public function findPaginated(int $contentTypeId, int $page, int $perPage, array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($contentTypeId, $filters);
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT e.*,
                    cs.system_key AS status_key, cs.label AS status_label, cs.is_public AS status_is_public
             FROM content_entries e
             JOIN content_statuses cs ON cs.id = e.status_id
             {$where}
             ORDER BY e.sort_order ASC, e.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countAll(int $contentTypeId, array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($contentTypeId, $filters);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM content_entries e
             JOIN content_statuses cs ON cs.id = e.status_id
             {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    public function create(array $data): int
    {
        return $this->model->insert($this->filterFields($data));
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $this->filterFields($data));
    }

    /** Soft delete (deleted_at) — non tocca lo status editoriale. */
    public function delete(int $id): void
    {
        $this->model->delete($id);
    }

    public function restore(int $id): void
    {
        $this->model->restore($id);
    }

    public function getValues(int $entryId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM content_entry_values WHERE entry_id = ? ORDER BY field_id ASC, sort_order ASC",
            [$entryId]
        );
    }

    /**
     * Sostituisce tutti i values dell'entry con $rows (ognuno un array
     * associativo di colonne di content_entry_values: field_id, sort_order,
     * value_text/value_number/... — quale colonna valorizzare è deciso dal
     * chiamante in base al Field Type, non da questo Repository).
     *
     * Delete+insert invece di upsert: i field di tipo repeater ammettono
     * più righe per lo stesso field_id, quindi non c'è una chiave naturale
     * su cui fare upsert riga-per-riga.
     */
    public function replaceValues(int $entryId, array $rows): void
    {
        $this->db->transaction(function () use ($entryId, $rows) {
            $this->db->execute("DELETE FROM content_entry_values WHERE entry_id = ?", [$entryId]);
            foreach ($rows as $row) {
                $this->valueModel->insert(['entry_id' => $entryId, ...$row]);
            }
        });
    }

    /** Gallery di un field su un'entry, ordinata per sort_order. */
    public function getGallery(int $entryId, int $fieldId): array
    {
        return $this->db->fetchAll(
            "SELECT cem.*, m.path, m.filename, m.mime_type, m.kind, m.is_public, m.alt, m.title AS media_title
             FROM content_entry_media cem
             JOIN media m ON m.id = cem.media_id
             WHERE cem.entry_id = ? AND cem.field_id = ?
             ORDER BY cem.sort_order ASC",
            [$entryId, $fieldId]
        );
    }

    /**
     * Sostituisce l'intera gallery di un field su un'entry (delete+insert,
     * stesso pattern di replaceValues). $items: array di
     * ['media_id' => int, 'caption' => ?string], l'ordine nell'array
     * diventa il sort_order.
     */
    public function replaceGallery(int $entryId, int $fieldId, array $items): void
    {
        $this->db->transaction(function () use ($entryId, $fieldId, $items) {
            $this->db->execute(
                "DELETE FROM content_entry_media WHERE entry_id = ? AND field_id = ?",
                [$entryId, $fieldId]
            );
            foreach (array_values($items) as $sortOrder => $item) {
                $this->db->execute(
                    "INSERT INTO content_entry_media (entry_id, field_id, media_id, sort_order, caption)
                     VALUES (?, ?, ?, ?, ?)",
                    [$entryId, $fieldId, (int) $item['media_id'], $sortOrder, $item['caption'] ?? null]
                );
            }
        });
    }

    /**
     * Termini di una tassonomia assegnati all'entry. Niente field_id: la
     * tassonomia non è mediata dal sistema Field/Field Group (Contratto
     * Theme↔CMS, $project->taxonomy('industry')) — lo scoping è per
     * taxonomy_id, non per field.
     */
    public function getTerms(int $entryId, int $taxonomyId): array
    {
        return $this->db->fetchAll(
            "SELECT tt.*
             FROM content_entry_terms cet
             JOIN taxonomy_terms tt ON tt.id = cet.term_id
             WHERE cet.entry_id = ? AND tt.taxonomy_id = ?
             ORDER BY tt.sort_order ASC, tt.label ASC",
            [$entryId, $taxonomyId]
        );
    }

    /**
     * Sostituisce l'assegnazione di UNA tassonomia sull'entry (delete+insert
     * scoped per taxonomy_id, stesso principio di replaceGallery scoped per
     * field_id): le assegnazioni di altre tassonomie sulla stessa entry non
     * vengono toccate.
     */
    public function replaceTerms(int $entryId, int $taxonomyId, array $termIds): void
    {
        $this->db->transaction(function () use ($entryId, $taxonomyId, $termIds) {
            $this->db->execute(
                "DELETE cet FROM content_entry_terms cet
                 JOIN taxonomy_terms tt ON tt.id = cet.term_id
                 WHERE cet.entry_id = ? AND tt.taxonomy_id = ?",
                [$entryId, $taxonomyId]
            );
            foreach ($termIds as $termId) {
                $this->db->execute(
                    "INSERT INTO content_entry_terms (entry_id, term_id) VALUES (?, ?)",
                    [$entryId, (int) $termId]
                );
            }
        });
    }

    /** Relazioni uscenti di un tipo da un'entry ("cosa punta questa entry"). */
    public function getRelations(int $entryId, string $relationType): array
    {
        return $this->db->fetchAll(
            "SELECT e.*, cer.sort_order
             FROM content_entry_relations cer
             JOIN content_entries e ON e.id = cer.related_entry_id
             WHERE cer.entry_id = ? AND cer.relation_type = ? AND e.deleted_at IS NULL
             ORDER BY cer.sort_order ASC",
            [$entryId, $relationType]
        );
    }

    /**
     * Relazione inversa ("chi punta a questa entry") — Invariante #8: è
     * sempre una query sulla stessa tabella, mai una seconda riga
     * sincronizzata a mano.
     */
    public function getInverseRelations(int $entryId, string $relationType): array
    {
        return $this->db->fetchAll(
            "SELECT e.*, cer.sort_order
             FROM content_entry_relations cer
             JOIN content_entries e ON e.id = cer.entry_id
             WHERE cer.related_entry_id = ? AND cer.relation_type = ? AND e.deleted_at IS NULL
             ORDER BY cer.sort_order ASC",
            [$entryId, $relationType]
        );
    }

    /**
     * Sostituisce le relazioni uscenti di UN relation_type dall'entry
     * (delete+insert scoped per relation_type, stesso principio di
     * replaceTerms scoped per taxonomy_id): altri relation_type sulla
     * stessa entry non vengono toccati. L'ordine di $relatedEntryIds
     * diventa il sort_order.
     */
    public function replaceRelations(int $entryId, string $relationType, array $relatedEntryIds): void
    {
        $this->db->transaction(function () use ($entryId, $relationType, $relatedEntryIds) {
            $this->db->execute(
                "DELETE FROM content_entry_relations WHERE entry_id = ? AND relation_type = ?",
                [$entryId, $relationType]
            );
            foreach (array_values($relatedEntryIds) as $sortOrder => $relatedEntryId) {
                $this->db->execute(
                    "INSERT INTO content_entry_relations (entry_id, related_entry_id, relation_type, sort_order)
                     VALUES (?, ?, ?, ?)",
                    [$entryId, (int) $relatedEntryId, $relationType, $sortOrder]
                );
            }
        });
    }

    private function filterFields(array $data): array
    {
        $allowed = [
            'content_type_id',
            'status_id',
            'published_at',
            'published_until',
            'include_in_archive',
            'indexable',
            'sort_order',
            'is_featured',
            'template',
        ];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }

    private function buildWhere(int $contentTypeId, array $filters): array
    {
        $conditions = ['e.content_type_id = ?', 'e.deleted_at IS NULL'];
        $params     = [$contentTypeId];

        if (!empty($filters['status_id'])) {
            $conditions[] = 'e.status_id = ?';
            $params[]     = (int) $filters['status_id'];
        }
        if (!empty($filters['is_featured'])) {
            $conditions[] = 'e.is_featured = 1';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }
}
