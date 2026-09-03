<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\MenuItem;

class MenuItemRepository
{
    private Database $db;
    private MenuItem $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new MenuItem();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    /** Piatta, ordinata per sort_order — costruire l'albero è compito del chiamante (Service/Theme). */
    public function findByMenu(int $menuId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM menu_items WHERE menu_id = ? ORDER BY sort_order ASC",
            [$menuId]
        );
    }

    public function findChildren(int $parentId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM menu_items WHERE parent_id = ? ORDER BY sort_order ASC",
            [$parentId]
        );
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
        return $this->db->execute("DELETE FROM menu_items WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = [
            'menu_id',
            'parent_id',
            'label',
            'target_variant',
            'target_entry_id',
            'target_url',
            'sort_order',
        ];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
