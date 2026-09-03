<?php

namespace Giga\Cms\Storage;

/**
 * Comportamento di produzione — unico usato quando MediaStorage è
 * istanziata di default (nessun mover passato esplicitamente).
 * move_uploaded_file() verifica che $tmpPath provenga davvero da un
 * upload HTTP gestito da PHP, protezione contro un tmp_name manomesso
 * in una richiesta reale.
 */
class HttpUploadMover implements UploadMoverInterface
{
    public function move(string $tmpPath, string $destPath): bool
    {
        return move_uploaded_file($tmpPath, $destPath);
    }
}
