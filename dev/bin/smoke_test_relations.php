<?php

/**
 * Smoke test manuale di ContentEntryService::getRelations/getInverseRelations
 * /replaceRelations — in particolare che la relazione inversa (Invariante #8)
 * sia davvero una query, non una riga separata.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\ContentEntryRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$typeService  = new ContentTypeService();
$entryService = new ContentEntryService();
$statusRepo   = new ContentStatusRepository();
$entryRepo    = new ContentEntryRepository();

$typeId      = $typeService->create(['slug' => 'relations-test', 'label' => 'RT', 'label_singular' => 'RT']);
$draftStatus = $statusRepo->findBySystemKey('draft');

$projectA = $entryRepo->create(['content_type_id' => $typeId, 'status_id' => $draftStatus['id'], 'sort_order' => 1]);
$projectB = $entryRepo->create(['content_type_id' => $typeId, 'status_id' => $draftStatus['id'], 'sort_order' => 2]);
$projectC = $entryRepo->create(['content_type_id' => $typeId, 'status_id' => $draftStatus['id'], 'sort_order' => 3]);

line("replaceRelations: A -['related_projects']-> [B, C], in quest'ordine");
$entryService->replaceRelations($projectA, 'related_projects', [$projectB, $projectC]);
foreach ($entryService->getRelations($projectA, 'related_projects') as $r) {
    printf("  A -> #%d sort=%d\n", $r['id'], $r['sort_order']);
}

line("getInverseRelations su B: chi ha 'related_projects' verso B? (deve trovare A, senza nessuna riga scritta da B)");
$inverseB = $entryService->getInverseRelations($projectB, 'related_projects');
foreach ($inverseB as $r) {
    printf("  #%d -> B\n", $r['id']);
}
if (count($inverseB) !== 1 || (int) $inverseB[0]['id'] !== $projectA) {
    echo "  ERRORE: atteso solo A come relazione inversa di B!\n";
    exit(1);
}

line('conferma diretta sul DB: nessuna riga con entry_id=B esiste (la relazione inversa non è mai stata scritta)');
$db  = \Giga\Core\Database::getInstance();
$row = $db->fetchOne(
    "SELECT COUNT(*) AS total FROM content_entry_relations WHERE entry_id = ?",
    [$projectB]
);
echo '  righe con entry_id=B: ' . $row['total'] . " (atteso 0)\n";
if ((int) $row['total'] !== 0) {
    echo "  ERRORE: e' stata scritta una riga inversa, non doveva succedere!\n";
    exit(1);
}

line("relation_type diverso ('similar_to') su A non interferisce con 'related_projects'");
$entryService->replaceRelations($projectA, 'similar_to', [$projectC]);
echo '  related_projects di A: ' . count($entryService->getRelations($projectA, 'related_projects')) . " (atteso 2, invariato)\n";
echo '  similar_to di A: ' . count($entryService->getRelations($projectA, 'similar_to')) . " (atteso 1)\n";
if (count($entryService->getRelations($projectA, 'related_projects')) !== 2) {
    echo "  ERRORE: replaceRelations su un relation_type ha toccato un altro relation_type!\n";
    exit(1);
}

line("replaceRelations con auto-relazione (A -> A) -> deve fallire");
try {
    $entryService->replaceRelations($projectA, 'related_projects', [$projectA]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("replaceRelations verso un'entry inesistente -> deve fallire per FK");
try {
    $entryService->replaceRelations($projectA, 'related_projects', [999999]);
    echo "  ERRORE: nessuna eccezione, la FK non ha protetto!\n";
    exit(1);
} catch (\PDOException $e) {
    echo '  bloccato correttamente dalla FK: ' . $e->getMessage() . "\n";
}

line("conferma: relation_type invariato dopo il tentativo fallito");
echo '  related_projects di A dopo il tentativo fallito: ' . count($entryService->getRelations($projectA, 'related_projects')) . " (atteso 2)\n";

line('Scioglimento pulito: cancello A (CASCADE ripulisce le sue relazioni uscenti)');
$entryRepo->delete($projectA); // soft delete, non tocca le FK
$db->execute("DELETE FROM content_entries WHERE id = ?", [$projectA]); // hard delete per verificare il CASCADE
$remaining = $db->fetchOne("SELECT COUNT(*) AS total FROM content_entry_relations WHERE entry_id = ? OR related_entry_id = ?", [$projectA, $projectA]);
echo '  righe di relazione rimaste che coinvolgono A: ' . $remaining['total'] . " (atteso 0)\n";
if ((int) $remaining['total'] !== 0) {
    echo "  ERRORE: il CASCADE non ha ripulito le relazioni!\n";
    exit(1);
}

line('done — tutti i controlli passati');
