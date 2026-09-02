<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\Taxonomy;

class TaxonomyRepository
{
    private Database $db;
    private Taxonomy $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new Taxonomy();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetchOne("SELECT * FROM taxonomies WHERE slug = ? LIMIT 1", [$slug]);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM taxonomies WHERE slug = ? AND id != ?",
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
        return $this->db->execute("DELETE FROM taxonomies WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = ['slug', 'label', 'label_singular', 'is_hierarchical'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
