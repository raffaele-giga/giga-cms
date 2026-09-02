<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\Language;

class LanguageRepository
{
    private Database $db;
    private Language $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new Language();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findByCode(string $code): array|false
    {
        return $this->db->fetchOne("SELECT * FROM languages WHERE code = ? LIMIT 1", [$code]);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function findActive(): array
    {
        return $this->db->fetchAll("SELECT * FROM languages WHERE active = 1 ORDER BY is_default DESC, name ASC");
    }

    public function findDefault(): array|false
    {
        return $this->db->fetchOne("SELECT * FROM languages WHERE is_default = 1 LIMIT 1");
    }

    public function codeExists(string $code, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM languages WHERE code = ? AND id != ?",
            [$code, $excludeId]
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
        return $this->db->execute("DELETE FROM languages WHERE id = ?", [$id]) > 0;
    }

    /** Toglie is_default a tutte le lingue tranne (eventualmente) $exceptId. */
    public function clearDefaultExcept(int $exceptId = 0): void
    {
        $this->db->execute("UPDATE languages SET is_default = 0 WHERE id != ?", [$exceptId]);
    }

    private function filterFields(array $data): array
    {
        $allowed = ['code', 'name', 'locale', 'is_default', 'active'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
