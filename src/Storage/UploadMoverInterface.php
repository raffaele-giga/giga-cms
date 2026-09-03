<?php

namespace Giga\Cms\Storage;

/**
 * Sposta un file dalla posizione temporanea alla destinazione finale in
 * private_path. Un'interfaccia esplicita invece di un rilevamento
 * d'ambiente (es. PHP_SAPI) dentro MediaStorage: chi vuole un
 * comportamento diverso da quello di produzione lo dichiara iniettando
 * un'implementazione diversa — il codice di sicurezza non lo deduce da
 * sé dall'ambiente in cui gira.
 */
interface UploadMoverInterface
{
    public function move(string $tmpPath, string $destPath): bool;
}
