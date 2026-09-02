<?php

/**
 * Smoke test manuale dei Repository del Content Engine, contro il DB
 * disposable di dev/. Non un test automatizzato/asserzioni — stampa lo
 * stato a ogni passo per ispezione visiva. Riusabile a ogni cambiamento
 * ai Repository/Model.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\ContentEntryRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$typeRepo   = new ContentTypeRepository();
$statusRepo = new ContentStatusRepository();
$entryRepo  = new ContentEntryRepository();

line('ContentStatusRepository::findAll');
foreach ($statusRepo->findAll() as $status) {
    printf(
        "  #%d %-10s public=%d terminal=%d\n",
        $status['id'],
        $status['system_key'],
        $status['is_public'],
        $status['is_terminal']
    );
}

line('ContentTypeRepository::create (Project)');
$projectTypeId = $typeRepo->create([
    'slug'              => 'projects',
    'label'             => 'Projects',
    'label_singular'    => 'Project',
    'supports_media'    => true,
    'supports_featured' => true,
    'default_ordering'  => 'created_at',
    'admin_icon'        => 'folder',
    'admin_order'       => 10,
]);
echo "  creato content_type id={$projectTypeId}\n";

line('ContentTypeRepository::findBySlug');
$projectType = $typeRepo->findBySlug('projects');
echo '  ' . json_encode($projectType, JSON_UNESCAPED_SLASHES) . "\n";

line('ContentTypeRepository::slugExists');
var_dump($typeRepo->slugExists('projects'));
var_dump($typeRepo->slugExists('news'));

$draftStatus     = $statusRepo->findBySystemKey('draft');
$publishedStatus = $statusRepo->findBySystemKey('published');

line('ContentEntryRepository::create (2 entries)');
$entry1Id = $entryRepo->create([
    'content_type_id' => $projectTypeId,
    'status_id'       => $draftStatus['id'],
    'sort_order'      => 1,
]);
$entry2Id = $entryRepo->create([
    'content_type_id' => $projectTypeId,
    'status_id'       => $publishedStatus['id'],
    'published_at'    => date('Y-m-d H:i:s'),
    'is_featured'     => true,
    'sort_order'      => 2,
]);
echo "  creati entry id={$entry1Id} (draft), id={$entry2Id} (published, featured)\n";

line('ContentEntryRepository::findById (entry 2, con join type/status)');
echo '  ' . json_encode($entryRepo->findById($entry2Id), JSON_UNESCAPED_SLASHES) . "\n";

line('ContentEntryRepository::replaceValues su entry 1 (client=text, year=number)');
$entryRepo->replaceValues($entry1Id, [
    ['field_id' => 101, 'value_text' => 'Acme Spa'],
    ['field_id' => 102, 'value_number' => 2026],
]);
echo '  values: ' . json_encode($entryRepo->getValues($entry1Id), JSON_UNESCAPED_SLASHES) . "\n";

line('ContentEntryRepository::replaceValues sovrascrive (repeater con 2 righe sullo stesso field_id)');
$entryRepo->replaceValues($entry1Id, [
    ['field_id' => 103, 'sort_order' => 0, 'value_text' => 'Riga repeater 1'],
    ['field_id' => 103, 'sort_order' => 1, 'value_text' => 'Riga repeater 2'],
]);
echo '  values dopo replace: ' . json_encode($entryRepo->getValues($entry1Id), JSON_UNESCAPED_SLASHES) . "\n";

line('ContentEntryRepository::findPaginated (content_type_id=' . $projectTypeId . ')');
foreach ($entryRepo->findPaginated($projectTypeId, 1, 10) as $row) {
    printf("  #%d status=%s featured=%d sort=%d\n", $row['id'], $row['status_key'], $row['is_featured'], $row['sort_order']);
}
echo '  countAll: ' . $entryRepo->countAll($projectTypeId) . "\n";
echo '  countAll (solo featured): ' . $entryRepo->countAll($projectTypeId, ['is_featured' => true]) . "\n";

line('ContentEntryRepository::delete (soft) su entry 1');
$entryRepo->delete($entry1Id);
var_dump($entryRepo->findById($entry1Id));
echo '  countAll dopo soft delete: ' . $entryRepo->countAll($projectTypeId) . "\n";

line('ContentEntryRepository::restore su entry 1');
$entryRepo->restore($entry1Id);
echo '  countAll dopo restore: ' . $entryRepo->countAll($projectTypeId) . "\n";

line('FK RESTRICT: provo a cancellare il content_type con entries collegate (deve fallire)');
try {
    $typeRepo->delete($projectTypeId);
    echo "  ERRORE: la delete e' passata, non doveva!\n";
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

line('done');
