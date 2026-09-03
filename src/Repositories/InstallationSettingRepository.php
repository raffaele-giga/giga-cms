<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;

/**
 * Nessun Model: chiave-valore con PRIMARY KEY testuale (`key`), il Model
 * base assume un id surrogato numerico — stesso motivo per cui le pivot
 * pure non ne hanno uno.
 */
class InstallationSettingRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(string $key): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT value FROM installation_settings WHERE `key` = ? LIMIT 1",
            [$key]
        );
        return $row ? $row['value'] : null;
    }

    /** @return array<string, ?string> */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, value FROM installation_settings");
        return array_column($rows, 'value', 'key');
    }

    /** Upsert: una sola riga per key. Verifica l'esistenza della riga, non del value (che può essere legittimamente NULL). */
    public function set(string $key, ?string $value): void
    {
        if ($this->exists($key)) {
            $this->db->execute(
                "UPDATE installation_settings SET value = ? WHERE `key` = ?",
                [$value, $key]
            );
            return;
        }

        $this->db->execute(
            "INSERT INTO installation_settings (`key`, value) VALUES (?, ?)",
            [$key, $value]
        );
    }

    public function delete(string $key): void
    {
        $this->db->execute("DELETE FROM installation_settings WHERE `key` = ?", [$key]);
    }

    private function exists(string $key): bool
    {
        return $this->db->fetchOne("SELECT `key` FROM installation_settings WHERE `key` = ?", [$key]) !== false;
    }
}
