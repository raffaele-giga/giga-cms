<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\BlockType;

class BlockTypeRepository
{
    private Database $db;
    private BlockType $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new BlockType();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetchOne("SELECT * FROM block_types WHERE slug = ? LIMIT 1", [$slug]);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM block_types WHERE slug = ? AND id != ?",
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
        return $this->db->execute("DELETE FROM block_types WHERE id = ?", [$id]) > 0;
    }

    /** Field Group che forniscono lo schema dati del Block Type, ordinati per sort_order dell'associazione. */
    public function getFieldGroups(int $blockTypeId): array
    {
        return $this->db->fetchAll(
            "SELECT fg.*, btfg.sort_order
             FROM block_type_field_groups btfg
             JOIN field_groups fg ON fg.id = btfg.field_group_id
             WHERE btfg.block_type_id = ?
             ORDER BY btfg.sort_order ASC",
            [$blockTypeId]
        );
    }

    /** Sostituisce l'intera assegnazione di Field Group (delete+insert, stesso pattern di ContentTypeRepository::syncFieldGroups). */
    public function syncFieldGroups(int $blockTypeId, array $fieldGroupIds): void
    {
        $this->db->transaction(function () use ($blockTypeId, $fieldGroupIds) {
            $this->db->execute("DELETE FROM block_type_field_groups WHERE block_type_id = ?", [$blockTypeId]);
            foreach (array_values($fieldGroupIds) as $sortOrder => $fieldGroupId) {
                $this->db->execute(
                    "INSERT INTO block_type_field_groups (block_type_id, field_group_id, sort_order) VALUES (?, ?, ?)",
                    [$blockTypeId, $fieldGroupId, $sortOrder]
                );
            }
        });
    }

    private function filterFields(array $data): array
    {
        $allowed = ['slug', 'label'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
