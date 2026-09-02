<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\TaxonomyTerm;

class TaxonomyTermRepository
{
    private Database $db;
    private TaxonomyTerm $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new TaxonomyTerm();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findBySlug(int $taxonomyId, string $slug): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM taxonomy_terms WHERE taxonomy_id = ? AND slug = ? LIMIT 1",
            [$taxonomyId, $slug]
        );
    }

    /** Tutti i termini di una tassonomia, piatti (parent_id incluso per chi vuole costruire l'albero). */
    public function findByTaxonomy(int $taxonomyId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM taxonomy_terms WHERE taxonomy_id = ? ORDER BY sort_order ASC, label ASC",
            [$taxonomyId]
        );
    }

    public function findChildren(int $parentId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM taxonomy_terms WHERE parent_id = ? ORDER BY sort_order ASC, label ASC",
            [$parentId]
        );
    }

    public function slugExists(int $taxonomyId, string $slug, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM taxonomy_terms WHERE taxonomy_id = ? AND slug = ? AND id != ?",
            [$taxonomyId, $slug, $excludeId]
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
        return $this->db->execute("DELETE FROM taxonomy_terms WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = ['taxonomy_id', 'parent_id', 'slug', 'label', 'sort_order'];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
