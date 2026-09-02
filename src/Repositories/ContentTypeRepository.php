<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\ContentType;

class ContentTypeRepository
{
    private Database $db;
    private ContentType $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new ContentType();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findBySlug(string $slug): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM content_types WHERE slug = ? LIMIT 1",
            [$slug]
        );
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function findAllForAdminMenu(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM content_types
             WHERE admin_enabled = 1
             ORDER BY admin_order ASC, label ASC"
        );
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM content_types WHERE slug = ? AND id != ?",
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
        return $this->db->execute("DELETE FROM content_types WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = [
            'slug',
            'label',
            'label_singular',
            'is_system',
            'supports_archive',
            'supports_seo',
            'supports_media',
            'supports_featured',
            'default_ordering',
            'template',
            'include_in_sitemap',
            'admin_icon',
            'admin_menu',
            'admin_order',
            'admin_enabled',
        ];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
