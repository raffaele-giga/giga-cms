<?php

/**
 * Bootstrap del harness di test locale (use-e-getta, non nel pacchetto
 * pubblicato). Autoloader minimale per Giga\Core\* / Giga\Cms\*, senza
 * Composer: evita di dover risolvere giga-core come dipendenza reale
 * solo per far girare questi script di verifica.
 *
 * App\* risolve a dev/support/App/ — uno stand-in che replica il codice
 * di progetto reale (PermissionService) per verificare il contratto
 * "Service permission-agnostic + enforcement lato Controller" senza
 * costruire un vero Controller/admin. Non è mai parte di giga-cms.
 */

define('ROOT_PATH', __DIR__);

spl_autoload_register(function (string $class): void {
    $map = [
        'Giga\\Core\\' => dirname(__DIR__, 2) . '/giga-core/src/',
        'Giga\\Cms\\'  => dirname(__DIR__, 2) . '/giga-cms/src/',
        'App\\'        => __DIR__ . '/support/App/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
