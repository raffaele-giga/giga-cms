<?php

namespace Giga\Cms\Repositories;

use Giga\Core\Database;

/**
 * Nessun Model: entry_id è PRIMARY KEY testuale/numerico non surrogato,
 * stesso motivo di InstallationSettingRepository.
 *
 * recordView() è l'unico punto di scrittura: un UPDATE atomico (o INSERT
 * se la riga non esiste ancora) fatto direttamente in SQL, mai un
 * find()+set() dell'applicazione — due richieste concorrenti sulla
 * stessa entry non devono poter perdere un conteggio per una race
 * condition letta-poi-scritta. Chiamato dal frontend pubblico a ogni
 * visualizzazione, mai dal path di scrittura editoriale (ContentEntryService).
 */
class ContentEntryStatRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function recordView(int $entryId): void
    {
        $this->db->execute(
            "INSERT INTO content_entry_stats (entry_id, view_count, last_viewed_at)
                VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
                view_count = view_count + 1,
                last_viewed_at = NOW()",
            [$entryId]
        );
    }

    /** Righe mai visitate non esistono ancora: default a zero, mai un errore. */
    public function find(int $entryId): array
    {
        $row = $this->db->fetchOne(
            "SELECT entry_id, view_count, last_viewed_at FROM content_entry_stats WHERE entry_id = ?",
            [$entryId]
        );

        return $row ?: ['entry_id' => $entryId, 'view_count' => 0, 'last_viewed_at' => null];
    }
}
