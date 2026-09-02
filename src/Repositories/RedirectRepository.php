<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\Redirect;

class RedirectRepository
{
    private Database $db;
    private Redirect $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new Redirect();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findBySource(string $source): array|false
    {
        return $this->db->fetchOne("SELECT * FROM redirects WHERE source = ? LIMIT 1", [$source]);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
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
        return $this->db->execute("DELETE FROM redirects WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = ['source', 'destination', 'status_code', 'active'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
