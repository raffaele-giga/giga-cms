<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\Media;

class MediaRepository
{
    private Database $db;
    private Media $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new Media();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findPaginated(int $page, int $perPage, array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT * FROM media {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countAll(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM media {$where}", $params);
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

    public function delete(int $id): bool
    {
        return $this->db->execute("DELETE FROM media WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = [
            'path',
            'filename',
            'mime_type',
            'kind',
            'size',
            'width',
            'height',
            'is_public',
            'alt',
            'title',
            'caption',
            'credit',
            'copyright',
            'focal_point',
        ];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['kind'])) {
            $conditions[] = 'kind = ?';
            $params[]     = $filters['kind'];
        }
        if (array_key_exists('is_public', $filters)) {
            $conditions[] = 'is_public = ?';
            $params[]     = (int) (bool) $filters['is_public'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
