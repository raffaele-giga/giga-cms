<?php

/**
 * Esempio della convenzione config/media.php che un progetto consumer
 * deve fornire (stesso pattern di config/database.php). Qui: due radici
 * fisicamente separate dentro l'harness use-e-getta — non rappresentativo
 * di un vero webroot, solo utile a dimostrare/testare la separazione.
 */

return [
    'public_path'  => __DIR__ . '/../public/media',
    'private_path' => __DIR__ . '/../storage/media',
    'public_url'   => '/media',
];
