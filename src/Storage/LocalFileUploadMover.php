<?php

namespace Giga\Cms\Storage;

/**
 * rename() invece di move_uploaded_file() — per contesti dove $tmpPath
 * non è (e non deve essere) un file registrato da PHP come upload HTTP:
 * script CLI, es. smoke test o un futuro import one-shot di file già
 * presenti su disco (roadmap punto 10, data-migration da cms-neviobianchi).
 *
 * Mai il default: va iniettata esplicitamente da chi la vuole — chi
 * scrive il test dichiara "sto usando il mover locale", invece di
 * lasciare che MediaStorage lo deduca dall'ambiente in cui gira.
 */
class LocalFileUploadMover implements UploadMoverInterface
{
    public function move(string $tmpPath, string $destPath): bool
    {
        return rename($tmpPath, $destPath);
    }
}
