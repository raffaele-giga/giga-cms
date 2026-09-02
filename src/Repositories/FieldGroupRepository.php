<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\FieldGroup;

class FieldGroupRepository
{
    private Database $db;
    private FieldGroup $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new FieldGroup();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetchOne("SELECT * FROM field_groups WHERE slug = ? LIMIT 1", [$slug]);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM field_groups WHERE slug = ? AND id != ?",
            [$slug, $excludeId]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }

    public function create(array $data): int
    {
        return $this->model->insert($this->filterFields($data));
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $this->filterFields($data));
    }

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM field_groups WHERE id = ?", [$id]) > 0;
    }

    /** Field del gruppo, ordinati per sort_order dell'associazione (pivot M:N). */
    public function getFields(int $fieldGroupId): array
    {
        return $this->db->fetchAll(
            "SELECT f.*, fgf.sort_order
             FROM field_group_fields fgf
             JOIN fields f ON f.id = fgf.field_id
             WHERE fgf.field_group_id = ?
             ORDER BY fgf.sort_order ASC",
            [$fieldGroupId]
        );
    }

    /**
     * Sostituisce l'intera composizione del gruppo (delete+insert, stesso
     * pattern di ContentEntryRepository::replaceValues — niente chiave
     * naturale su cui fare upsert riga-per-riga). L'ordine di $fieldIds
     * diventa il sort_order.
     */
    public function syncFields(int $fieldGroupId, array $fieldIds): void
    {
        $this->db->transaction(function () use ($fieldGroupId, $fieldIds) {
            $this->db->execute("DELETE FROM field_group_fields WHERE field_group_id = ?", [$fieldGroupId]);
            foreach (array_values($fieldIds) as $sortOrder => $fieldId) {
                $this->db->execute(
                    "INSERT INTO field_group_fields (field_group_id, field_id, sort_order) VALUES (?, ?, ?)",
                    [$fieldGroupId, $fieldId, $sortOrder]
                );
            }
        });
    }

    private function filterFields(array $data): array
    {
        $allowed = ['slug', 'label', 'description'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
