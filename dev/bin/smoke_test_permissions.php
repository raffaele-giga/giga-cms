<?php

/**
 * Smoke test manuale di ContentTypeService::create/update/delete e della
 * sincronizzazione con sec_permissions (Decisione #4). Presuppone lo
 * schema sec_* applicato via dev/sql/sec_schema.sql.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Core\Database;
use Giga\Cms\Services\ContentTypeService;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

function dumpPermissions(Database $db, string $module): void
{
    $rows = $db->fetchAll(
        "SELECT p.module, p.action, p.resource_type,
                GROUP_CONCAT(r.slug ORDER BY r.slug) AS assigned_roles
         FROM sec_permissions p
         LEFT JOIN sec_role_permissions rp ON rp.permission_id = p.id
         LEFT JOIN sec_roles r ON r.id = rp.role_id
         WHERE p.module = ?
         GROUP BY p.id
         ORDER BY p.action",
        [$module]
    );
    foreach ($rows as $row) {
        printf(
            "  %s.%s resource_type=%s roles=%s\n",
            $row['module'],
            $row['action'],
            $row['resource_type'] ?? 'NULL',
            $row['assigned_roles'] ?? '(nessuno)'
        );
    }
}

$db      = Database::getInstance();
$service = new ContentTypeService();

line('create() con slug che collide con un module di sistema (settings) -> deve lanciare eccezione');
try {
    $service->create(['slug' => 'settings', 'label' => 'X', 'label_singular' => 'X']);
    echo "  ERRORE: nessuna eccezione lanciata!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('versione permessi di admin PRIMA della create');
$versionBefore = (int) $db->fetchOne("SELECT permissions_version FROM sec_roles WHERE slug='admin'")['permissions_version'];
echo "  permissions_version admin = {$versionBefore}\n";

line("create() Content Type 'services'");
$servicesId = $service->create([
    'slug'           => 'services',
    'label'          => 'Services',
    'label_singular' => 'Service',
]);
echo "  creato content_type id={$servicesId}\n";
dumpPermissions($db, 'services');

line('versione permessi di admin DOPO la create (deve essere incrementata)');
$versionAfter = (int) $db->fetchOne("SELECT permissions_version FROM sec_roles WHERE slug='admin'")['permissions_version'];
echo "  permissions_version admin = {$versionAfter}\n";
if ($versionAfter <= $versionBefore) {
    echo "  ERRORE: permissions_version non incrementata!\n";
    exit(1);
}

line("create() con lo stesso slug 'services' -> deve lanciare eccezione (duplicato)");
try {
    $service->create(['slug' => 'services', 'label' => 'Dup', 'label_singular' => 'Dup']);
    echo "  ERRORE: nessuna eccezione lanciata!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("update() rename slug 'services' -> 'our-services'");
$service->update($servicesId, ['slug' => 'our-services']);
echo "  permessi sotto il vecchio slug 'services':\n";
dumpPermissions($db, 'services');
echo "  permessi sotto il nuovo slug 'our-services':\n";
dumpPermissions($db, 'our-services');

line('conferma: le assegnazioni di ruolo sono sopravvissute al rename (stesso permission_id)');
$row = $db->fetchOne(
    "SELECT COUNT(*) AS total FROM sec_role_permissions rp
     JOIN sec_permissions p ON p.id = rp.permission_id
     WHERE p.module = 'our-services'"
);
echo '  assegnazioni role_permissions sotto il nuovo slug: ' . $row['total'] . " (atteso 5)\n";
if ((int) $row['total'] !== 5) {
    echo "  ERRORE: assegnazioni perse nel rename!\n";
    exit(1);
}

line("delete() Content Type 'our-services'");
$service->delete($servicesId);
echo "  permessi rimasti sotto 'our-services' (atteso: nessuno):\n";
dumpPermissions($db, 'our-services');
$remaining = $db->fetchOne("SELECT COUNT(*) AS total FROM sec_permissions WHERE module = 'our-services'");
echo '  righe sec_permissions rimaste: ' . $remaining['total'] . " (atteso 0)\n";
if ((int) $remaining['total'] !== 0) {
    echo "  ERRORE: permessi non ripuliti dopo la delete!\n";
    exit(1);
}

line('FK RESTRICT: creo un Content Type con una entry collegata, poi provo a cancellarlo (deve fallire, permessi intatti)');
$projectsId = $service->create([
    'slug'           => 'projects-fk-test',
    'label'          => 'Projects FK Test',
    'label_singular' => 'Project',
]);
$draftStatus = $db->fetchOne("SELECT id FROM content_statuses WHERE system_key='draft'");
$db->insert(
    "INSERT INTO content_entries (content_type_id, status_id) VALUES (?, ?)",
    [$projectsId, $draftStatus['id']]
);
try {
    $service->delete($projectsId);
    echo "  ERRORE: la delete e' passata, non doveva (FK RESTRICT)!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}
echo "  permessi ancora presenti dopo il tentativo fallito (la transazione ha fatto rollback):\n";
dumpPermissions($db, 'projects-fk-test');
$stillThere = $db->fetchOne("SELECT COUNT(*) AS total FROM sec_permissions WHERE module = 'projects-fk-test'");
if ((int) $stillThere['total'] !== 5) {
    echo "  ERRORE: la transazione non ha fatto rollback correttamente, permessi persi nonostante il fallimento!\n";
    exit(1);
}

line('done — tutti i controlli passati');
