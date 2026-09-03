<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;
use Giga\Cms\Models\ContentEntryTranslation;

class ContentEntryTranslationRepository
{
    private Database $db;
    private ContentEntryTranslation $model;

    public function __construct()
    {
        $this->db    = Database::getInstance();
        $this->model = new ContentEntryTranslation();
    }

    public function findById(int $id): array|false
    {
        return $this->model->find($id);
    }

    public function findByEntryAndLanguage(int $entryId, int $languageId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM content_entry_translations WHERE entry_id = ? AND language_id = ? LIMIT 1",
            [$entryId, $languageId]
        );
    }

    /** Tutte le traduzioni di un'entry, una per lingua. */
    public function findByEntry(int $entryId): array
    {
        return $this->db->fetchAll(
            "SELECT ct.*, l.code AS language_code, l.name AS language_name
             FROM content_entry_translations ct
             JOIN languages l ON l.id = ct.language_id
             WHERE ct.entry_id = ?
             ORDER BY l.is_default DESC, l.name ASC",
            [$entryId]
        );
    }

    public function slugExists(int $contentTypeId, int $languageId, string $slug, int $excludeId = 0): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM content_entry_translations
             WHERE content_type_id = ? AND language_id = ? AND slug = ? AND id != ?",
            [$contentTypeId, $languageId, $slug, $excludeId]
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
        return $this->db->execute("DELETE FROM content_entry_translations WHERE id = ?", [$id]) > 0;
    }

    private function filterFields(array $data): array
    {
        $allowed = [
            'entry_id',
            'content_type_id',
            'language_id',
            'title',
            'slug',
            'meta_title',
            'meta_description',
            'canonical_url',
            'og_title',
            'og_description',
            'og_media_id',
        ];
        return array_filter($data, fn($key) => in_array($key, $allowed), ARRAY_FILTER_USE_KEY);
    }
}
