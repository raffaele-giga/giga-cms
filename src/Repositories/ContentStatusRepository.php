<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\ContentStatus;

class ContentStatusRepository
{
    private Database $db;
    private ContentStatus $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new ContentStatus();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function findBySystemKey(string $systemKey): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM content_statuses WHERE system_key = ? LIMIT 1",
            [$systemKey]
        );
    }
}
