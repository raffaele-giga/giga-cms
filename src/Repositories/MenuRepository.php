<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\Menu;

class MenuRepository
{
    private Database $db;
    private Menu $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new Menu();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetchOne("SELECT * FROM menus WHERE slug = ? LIMIT 1", [$slug]);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM menus WHERE slug = ? AND id != ?",
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
        return $this->db->execute("DELETE FROM menus WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = ['slug', 'label'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
