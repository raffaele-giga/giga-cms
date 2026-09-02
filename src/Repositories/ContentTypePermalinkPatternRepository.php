<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;

/**
 * Nessun Model: PRIMARY KEY composita (content_type_id, language_id), il
 * Model base assume un id surrogato singolo — stesso motivo per cui le
 * pivot pure (field_group_fields, content_type_field_groups) non ne hanno uno.
 */
class ContentTypePermalinkPatternRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $contentTypeId, int $languageId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM content_type_permalink_patterns WHERE content_type_id = ? AND language_id = ? LIMIT 1",
            [$contentTypeId, $languageId]
        );
    }

    /** Tutti gli override di un Content Type, uno per lingua. */
    public function findByContentType(int $contentTypeId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, l.code AS language_code, l.name AS language_name
             FROM content_type_permalink_patterns p
             JOIN languages l ON l.id = p.language_id
             WHERE p.content_type_id = ?
             ORDER BY l.code ASC",
            [$contentTypeId]
        );
    }

    /** Upsert: un solo override per (content_type_id, language_id). */
    public function set(int $contentTypeId, int $languageId, string $pattern): void
    {
        if ($this->find($contentTypeId, $languageId)) {
            $this->db->execute(
                "UPDATE content_type_permalink_patterns SET pattern = ? WHERE content_type_id = ? AND language_id = ?",
                [$pattern, $contentTypeId, $languageId]
            );
            return;
        }

        $this->db->execute(
            "INSERT INTO content_type_permalink_patterns (content_type_id, language_id, pattern) VALUES (?, ?, ?)",
            [$contentTypeId, $languageId, $pattern]
        );
    }

    public function remove(int $contentTypeId, int $languageId): void
    {
        $this->db->execute(
            "DELETE FROM content_type_permalink_patterns WHERE content_type_id = ? AND language_id = ?",
            [$contentTypeId, $languageId]
        );
    }
}
