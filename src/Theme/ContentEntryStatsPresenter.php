<?php

namespace Giga\Cms\Theme;

/**
 * Metà "lettura" delle statistiche di una entry (Content Type
 * supports_stats). Nessuna entry vista pubblicamente ha ancora
 * accumulato una riga in content_entry_stats: view_count=0 e
 * last_viewed_at=null sono valori legittimi, non un errore — coerente
 * col fatto che ContentEntryStatRepository::find() già restituisce
 * questo default invece di lanciare un'eccezione.
 *
 * Esposto anche per Content Type con supports_stats=false: in quel caso
 * non esisterà mai una riga (Cms::recordView() non ne scrive), quindi si
 * legge sempre e solo il default zero — nessun controllo di capability
 * necessario qui.
 */
class ContentEntryStatsPresenter
{
    public function __construct(private array $row)
    {
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'view_count'     => (int) $this->row['view_count'],
            'last_viewed_at' => $this->row['last_viewed_at'],
            default          => throw new \RuntimeException(
                "Proprietà '{$name}' non esposta dal Contratto Theme↔CMS."
            ),
        };
    }
}
