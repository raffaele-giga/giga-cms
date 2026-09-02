<?php

/**
 * Dimostra il contratto "Service permission-agnostic + enforcement lato
 * Controller" con permessi/audit REALI di giga-core (PermissionService è
 * uno stand-in fedele in dev/support/, AuditService è la classe vera).
 * Non un Controller reale, non UI — solo la sequenza esatta che un
 * Controller farebbe: requirePermission() PRIMA di invocare il Service,
 * AuditService::log() DOPO. L'admin vero arriva più avanti (Frontend).
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\PermissionService;
use Giga\Core\Database;
use Giga\Core\Services\AuditService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Repositories\ContentStatusRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

/** Replica BaseController::requirePermission() — qui senza una vera response 403, solo l'eccezione. */
function requirePermission(string $permission): void
{
    if (!PermissionService::can($permission)) {
        throw new \RuntimeException("403 Forbidden: manca il permesso '{$permission}'.");
    }
}

session_start();
$db = Database::getInstance();

$typeService  = new ContentTypeService();
$entryService = new ContentEntryService();
$audit        = new AuditService();

line("create() Content Type 'articles' -> genera le 5 righe di permesso, assegnate solo ad admin (Decisione #4)");
$typeId = $typeService->create(['slug' => 'articles', 'label' => 'Articles', 'label_singular' => 'Article']);
$editSlug = 'articles.edit';
echo "  content_type id={$typeId}, permesso da verificare: '{$editSlug}'\n";

$editorRole = $db->fetchOne("SELECT id FROM sec_roles WHERE slug = 'editor'");

line("Sessione: utente loggato come 'editor' (non admin, non superadmin)");
$_SESSION['user_role_id']   = (int) $editorRole['id'];
$_SESSION['user_role_slug'] = 'editor';
$_SESSION['user_id']        = 42;
$_SESSION['user_email']     = 'editor@example.com';

line("requirePermission('{$editSlug}') come editor -> deve fallire (default: solo admin ce l'ha)");
try {
    requirePermission($editSlug);
    echo "  ERRORE: nessuna eccezione, il controllo permessi non ha bloccato!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("Grant esplicito: assegna '{$editSlug}' al ruolo editor (simula un'azione admin non ancora costruita) + bump permissions_version");
$permission = $db->fetchOne(
    "SELECT id FROM sec_permissions WHERE module = 'articles' AND action = 'edit'"
);
$db->execute(
    "INSERT INTO sec_role_permissions (role_id, permission_id) VALUES (?, ?)",
    [$editorRole['id'], $permission['id']]
);
$db->execute("UPDATE sec_roles SET permissions_version = permissions_version + 1 WHERE id = ?", [$editorRole['id']]);
echo "  grant scritto in sec_role_permissions\n";

line("requirePermission('{$editSlug}') come editor, DOPO il grant -> deve passare (cache invalidata da permissions_version)");
requirePermission($editSlug);
echo "  permesso concesso, cache di sessione ricaricata correttamente\n";

line('ContentEntryService::create() — il Service non sa nulla di permessi, viene chiamato solo dopo il check');
$draftStatus = (new ContentStatusRepository())->findBySystemKey('draft');
$entryId     = $entryService->create(['content_type_id' => $typeId, 'status_id' => $draftStatus['id']]);
echo "  entry creata id={$entryId}\n";

line('AuditService::log() reale, DOPO la chiamata al Service — pattern esatto dei Controller Admin di wos-pro/cms-neviobianchi');
$audit->log(
    'content_entry_created',
    $_SESSION['user_id'],
    $_SESSION['user_email'],
    'content_entry',
    $entryId
);
$logs = $audit->getByEntity('content_entry', $entryId);
echo '  righe di audit trovate: ' . count($logs) . "\n";
foreach ($logs as $l) {
    printf("  #%d user=%s action=%s entity=%s:%d\n", $l['id'], $l['user_email'], $l['action'], $l['entity'], $l['entity_id']);
}
if (count($logs) !== 1) {
    echo "  ERRORE: attesa esattamente 1 riga di audit!\n";
    exit(1);
}

line("Sessione: passa a 'superadmin' -> requirePermission() bypassa SEMPRE, AuditService::log() NON scrive nulla");
$_SESSION['user_role_slug'] = 'superadmin';
requirePermission('qualunque.permesso.mai.concesso');
echo "  bypass superadmin confermato su requirePermission()\n";

$entryId2 = $entryService->create(['content_type_id' => $typeId, 'status_id' => $draftStatus['id']]);
$audit->log('content_entry_created', $_SESSION['user_id'], $_SESSION['user_email'], 'content_entry', $entryId2);
$logsSuperadmin = $audit->getByEntity('content_entry', $entryId2);
echo '  righe di audit per l\'entry creata da superadmin: ' . count($logsSuperadmin) . " (atteso 0)\n";
if (count($logsSuperadmin) !== 0) {
    echo "  ERRORE: AuditService doveva ignorare silenziosamente il superadmin!\n";
    exit(1);
}

line('done — contratto Service permission-agnostic + enforcement Controller verificato end-to-end');
