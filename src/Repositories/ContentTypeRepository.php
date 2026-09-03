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

    /** Field Group assegnati, ordinati per sort_order dell'associazione (pivot M:N). */
    public function getFieldGroups(int $contentTypeId): array
    {
        return $this->db->fetchAll(
            "SELECT fg.*, ctfg.sort_order
             FROM content_type_field_groups ctfg
             JOIN field_groups fg ON fg.id = ctfg.field_group_id
             WHERE ctfg.content_type_id = ?
             ORDER BY ctfg.sort_order ASC",
            [$contentTypeId]
        );
    }

    /**
     * Sostituisce l'intera assegnazione di Field Group del Content Type
     * (delete+insert, stesso pattern di ContentEntryRepository::replaceValues).
     * L'ordine di $fieldGroupIds diventa il sort_order.
     */
    public function syncFieldGroups(int $contentTypeId, array $fieldGroupIds): void
    {
        $this->db->transaction(function () use ($contentTypeId, $fieldGroupIds) {
            $this->db->execute(
                "DELETE FROM content_type_field_groups WHERE content_type_id = ?",
                [$contentTypeId]
            );
            foreach (array_values($fieldGroupIds) as $sortOrder => $fieldGroupId) {
                $this->db->execute(
                    "INSERT INTO content_type_field_groups (content_type_id, field_group_id, sort_order) VALUES (?, ?, ?)",
                    [$contentTypeId, $fieldGroupId, $sortOrder]
                );
            }
        });
    }

    /** Tassonomie assegnate, ordinate per sort_order dell'associazione (pivot M:N). */
    public function getTaxonomies(int $contentTypeId): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, ctt.sort_order
             FROM content_type_taxonomies ctt
             JOIN taxonomies t ON t.id = ctt.taxonomy_id
             WHERE ctt.content_type_id = ?
             ORDER BY ctt.sort_order ASC",
            [$contentTypeId]
        );
    }

    /**
     * Sostituisce l'intera assegnazione di tassonomie del Content Type
     * (delete+insert, stesso pattern di syncFieldGroups). L'ordine di
     * $taxonomyIds diventa il sort_order.
     */
    public function syncTaxonomies(int $contentTypeId, array $taxonomyIds): void
    {
        $this->db->transaction(function () use ($contentTypeId, $taxonomyIds) {
            $this->db->execute(
                "DELETE FROM content_type_taxonomies WHERE content_type_id = ?",
                [$contentTypeId]
            );
            foreach (array_values($taxonomyIds) as $sortOrder => $taxonomyId) {
                $this->db->execute(
                    "INSERT INTO content_type_taxonomies (content_type_id, taxonomy_id, sort_order) VALUES (?, ?, ?)",
                    [$contentTypeId, $taxonomyId, $sortOrder]
                );
            }
        });
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
            'permalink_pattern',
            'json_ld_type',
            'include_in_sitemap',
            'admin_icon',
            'admin_menu',
            'admin_order',
            'admin_enabled',
        ];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
