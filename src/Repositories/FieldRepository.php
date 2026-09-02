<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\Field;

class FieldRepository
{
    private Database $db;
    private Field $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new Field();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findByKey(string $key): array|false
    {
        return $this->db->fetchOne('SELECT * FROM fields WHERE `key` = ? LIMIT 1', [$key]);
    }

    public function findAll(): array
    {
        return $this->model->findAll();
    }

    public function keyExists(string $key, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM fields WHERE `key` = ? AND id != ?',
            [$key, $excludeId]
        );
        return (int) ($row['total'] ?? 0) > 0;
    }

    /**
     * Query diretta invece di Model::insert(): `key` è parola riservata SQL
     * e il CRUD generico del Model base non quota i nomi di colonna.
     */
    public function create(array $data): int
    {
        $data    = $this->filterFields($data);
        $columns = implode(', ', array_map($this->quoteColumn(...), array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        return (int) $this->db->insert(
            "INSERT INTO fields ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterFields($data);
        if ($data === []) {
            return false;
        }

        $sets = implode(', ', array_map(
            fn($col) => $this->quoteColumn($col) . ' = ?',
            array_keys($data)
        ));

        $affected = $this->db->execute(
            "UPDATE fields SET {$sets} WHERE id = ?",
            [...array_values($data), $id]
        );

        return $affected > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM fields WHERE id = ?', [$id]) > 0;
    }

    /** Gruppi in cui questo field è usato (pivot M:N, vista inversa). */
    public function getFieldGroups(int $fieldId): array
    {
        return $this->db->fetchAll(
            "SELECT fg.*, fgf.sort_order
             FROM field_group_fields fgf
             JOIN field_groups fg ON fg.id = fgf.field_group_id
             WHERE fgf.field_id = ?
             ORDER BY fg.label ASC",
            [$fieldId]
        );
    }

    private function quoteColumn(string $column): string
    {
        return $column === 'key' ? '`key`' : $column;
    }

    private function filterFields(array $data): array
    {
        $allowed = ['key', 'label', 'type', 'translatable', 'required', 'config'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
