<?php

/**
 * Smoke test di publish()/unpublish()/archive() e della regola "pubblico
 * effettivo" — soprattutto: verifica che la versione SQL
 * (ContentEntryRepository::effectivePublicCondition(), usata dal filtro
 * 'effectively_public' di findPaginated) e la versione PHP
 * (ContentEntryService::isEffectivelyPublic(), per un'entry già caricata)
 * concordino SEMPRE, per ogni combinazione di stato/date testata. Le due
 * non condividono un'implementazione letterale (SQL vs PHP), quindi
 * questo test è il meccanismo che le tiene allineate nel tempo.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\ContentStatusRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

/**
 * Crea un'entry nello stato/date indicati, poi verifica che il verdetto
 * PHP (isEffectivelyPublic su findById) coincida con la presenza/assenza
 * nella query SQL filtrata (findPaginated con effectively_public=true).
 */
function assertConsistent(
    ContentEntryService $service,
    ContentEntryRepository $repo,
    int $typeId,
    string $label,
    array $entryData,
    bool $expected
): void {
    $id    = $repo->create(array_merge(['content_type_id' => $typeId], $entryData));
    $entry = $service->getById($id);

    $phpVerdict = $service->isEffectivelyPublic($entry);

    $sqlRows  = $repo->findPaginated($typeId, 1, 100, ['effectively_public' => true]);
    $sqlIds   = array_map(fn($r) => (int) $r['id'], $sqlRows);
    $sqlVerdict = in_array($id, $sqlIds, true);

    $status = ($phpVerdict === $expected && $sqlVerdict === $expected) ? 'OK' : 'ERRORE';
    printf("  [%s] %-45s PHP=%s SQL=%s atteso=%s\n", $status, $label, var_export($phpVerdict, true), var_export($sqlVerdict, true), var_export($expected, true));

    if ($status === 'ERRORE') {
        throw new \RuntimeException("Disallineamento su '{$label}'");
    }
}

$typeService  = new ContentTypeService();
$entryService = new ContentEntryService();
$entryRepo    = new ContentEntryRepository();
$statusRepo   = new ContentStatusRepository();

$typeId = $typeService->create(['slug' => 'lifecycle-test', 'label' => 'LC', 'label_singular' => 'LC']);

$draft     = $statusRepo->findBySystemKey('draft');
$published = $statusRepo->findBySystemKey('published');
$archived  = $statusRepo->findBySystemKey('archived');

$past   = date('Y-m-d H:i:s', strtotime('-1 day'));
$future = date('Y-m-d H:i:s', strtotime('+1 day'));

line('Matrice stato/date -> pubblico effettivo (PHP e SQL devono concordare sempre)');

assertConsistent($entryService, $entryRepo, $typeId, 'draft, nessuna data', [
    'status_id' => $draft['id'],
], false);

assertConsistent($entryService, $entryRepo, $typeId, 'published, published_at passato, nessun until', [
    'status_id' => $published['id'], 'published_at' => $past,
], true);

assertConsistent($entryService, $entryRepo, $typeId, 'published, published_at FUTURO (schedulata)', [
    'status_id' => $published['id'], 'published_at' => $future,
], false);

assertConsistent($entryService, $entryRepo, $typeId, 'published, published_at passato, published_until passato (scaduta)', [
    'status_id' => $published['id'], 'published_at' => $past, 'published_until' => $past,
], false);

assertConsistent($entryService, $entryRepo, $typeId, 'published, published_at passato, published_until futuro', [
    'status_id' => $published['id'], 'published_at' => $past, 'published_until' => $future,
], true);

assertConsistent($entryService, $entryRepo, $typeId, 'published ma published_at NULL (mai dovrebbe capitare, ma testato)', [
    'status_id' => $published['id'],
], false);

assertConsistent($entryService, $entryRepo, $typeId, 'archived, published_at passato (is_public dello stato conta, non solo le date)', [
    'status_id' => $archived['id'], 'published_at' => $past,
], false);

line('publish() — pubblicazione immediata');
$id1 = $entryRepo->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);
$entryService->publish($id1);
$entry1 = $entryService->getById($id1);
echo "  status={$entry1['status_key']} published_at={$entry1['published_at']} isEffectivelyPublic=" . var_export($entryService->isEffectivelyPublic($entry1), true) . "\n";
if ($entry1['status_key'] !== 'published' || !$entryService->isEffectivelyPublic($entry1)) {
    echo "  ERRORE: publish() non ha funzionato come atteso!\n";
    exit(1);
}

line('publish() con data futura — scheduling esplicito');
$id2 = $entryRepo->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);
$entryService->publish($id2, $future);
$entry2 = $entryService->getById($id2);
echo "  status={$entry2['status_key']} published_at={$entry2['published_at']} isEffectivelyPublic=" . var_export($entryService->isEffectivelyPublic($entry2), true) . "\n";
if ($entryService->isEffectivelyPublic($entry2)) {
    echo "  ERRORE: un'entry schedulata nel futuro non deve risultare pubblica ora!\n";
    exit(1);
}

line('unpublish() — torna a draft');
$entryService->unpublish($id1);
$entry1 = $entryService->getById($id1);
echo "  status={$entry1['status_key']} isEffectivelyPublic=" . var_export($entryService->isEffectivelyPublic($entry1), true) . "\n";
if ($entry1['status_key'] !== 'draft' || $entryService->isEffectivelyPublic($entry1)) {
    echo "  ERRORE: unpublish() non ha funzionato come atteso!\n";
    exit(1);
}

line('archive()');
$entryService->archive($id2);
$entry2 = $entryService->getById($id2);
echo "  status={$entry2['status_key']} isEffectivelyPublic=" . var_export($entryService->isEffectivelyPublic($entry2), true) . "\n";
if ($entry2['status_key'] !== 'archived') {
    echo "  ERRORE: archive() non ha funzionato come atteso!\n";
    exit(1);
}

line("publish()/unpublish()/archive() su entry inesistente -> devono fallire (getById())");
foreach (['publish', 'unpublish', 'archive'] as $method) {
    try {
        $entryService->$method(999999);
        echo "  ERRORE: {$method}() non ha lanciato eccezione!\n";
        exit(1);
    } catch (\RuntimeException $e) {
        echo "  {$method}() bloccato correttamente: " . $e->getMessage() . "\n";
    }
}

line('done — tutti i controlli passati, PHP e SQL concordano su ogni caso testato');
