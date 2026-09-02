<?php

/**
 * CLI di test per il Migration Runner di giga-core, usata solo dentro
 * dev/ (harness locale, use-e-getta) per verificare le migration di
 * giga-cms in isolamento — non è lo script bin/migrate.php di produzione,
 * che vive nel progetto consumer reale (vedi Giga\Core\Migrations\Migrator).
 *
 * Uso: php dev/bin/migrate.php <package> [run|rollback|status] [--steps=N]
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Core\Migrations\Migrator;

$package = $argv[1] ?? null;
$action  = $argv[2] ?? 'run';

if ($package === null) {
    fwrite(STDERR, "Uso: php dev/bin/migrate.php <package> [run|rollback|status] [--steps=N]\n");
    exit(1);
}

$migrationsPath = match ($package) {
    'giga-cms' => dirname(__DIR__, 2) . '/src/Migrations',
    default    => null,
};

if ($migrationsPath === null) {
    fwrite(STDERR, "Pacchetto sconosciuto: {$package}\n");
    exit(1);
}

$migrator = new Migrator();

switch ($action) {
    case 'run':
        $applied = $migrator->run($package, $migrationsPath);
        echo $applied === []
            ? "Nessuna migration da applicare.\n"
            : "Applicate:\n - " . implode("\n - ", $applied) . "\n";
        break;

    case 'rollback':
        $steps = 1;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--steps=')) {
                $steps = (int) substr($arg, 8);
            }
        }
        $rolledBack = $migrator->rollback($package, $migrationsPath, $steps);
        echo $rolledBack === []
            ? "Niente da annullare.\n"
            : "Annullate:\n - " . implode("\n - ", $rolledBack) . "\n";
        break;

    case 'status':
        $status = $migrator->status($package, $migrationsPath);
        echo "Applicate:\n";
        foreach ($status['applied'] as $m) {
            echo " - {$m}\n";
        }
        echo "In sospeso:\n";
        foreach ($status['pending'] as $m) {
            echo " - {$m}\n";
        }
        break;

    default:
        fwrite(STDERR, "Azione sconosciuta: {$action}\n");
        exit(1);
}
