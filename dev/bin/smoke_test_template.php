<?php

/**
 * Smoke test di template_options: nessun vincolo se non dichiarato
 * (retrocompatibile), validazione sia sul template di default del
 * Content Type sia sull'override per-entry quando dichiarato.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Repositories\ContentStatusRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$typeService  = new ContentTypeService();
$entryService = new ContentEntryService();
$statusRepo   = new ContentStatusRepository();
$draft        = $statusRepo->findBySystemKey('draft');

line("create() Content Type SENZA template_options -> template resta libero (retrocompatibile)");
$freeTypeId = $typeService->create([
    'slug' => 'template-free', 'label' => 'Free', 'label_singular' => 'Free',
    'template' => 'qualunque-stringa',
]);
$freeType = $typeService->getById($freeTypeId);
echo "  template={$freeType['template']} template_options=" . json_encode($freeType['template_options']) . "\n";
if ($freeType['template'] !== 'qualunque-stringa') {
    echo "  ERRORE: senza template_options il template deve restare libero!\n";
    exit(1);
}

line("create() Content Type CON template_options, template di default valido");
$typeId = $typeService->create([
    'slug' => 'template-test', 'label' => 'Template Test', 'label_singular' => 'Template Test',
    'template' => 'default',
    'template_options' => ['default', 'full-width', 'case-study'],
]);
$type = $typeService->getById($typeId);
echo "  template={$type['template']} template_options=" . json_encode($type['template_options']) . "\n";
if ($type['template_options'] !== ['default', 'full-width', 'case-study']) {
    echo "  ERRORE: template_options non salvate/decodificate correttamente!\n";
    exit(1);
}

line("create() con template di default NON tra le opzioni -> deve fallire");
try {
    $typeService->create([
        'slug' => 'template-bad', 'label' => 'Bad', 'label_singular' => 'Bad',
        'template' => 'inesistente',
        'template_options' => ['default', 'full-width'],
    ]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("update() cambia template_options togliendo il template attualmente in uso -> deve fallire");
try {
    $typeService->update($typeId, ['template_options' => ['full-width', 'case-study']]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("update() cambia SOLO il template (restando tra le opzioni dichiarate) -> deve passare");
$typeService->update($typeId, ['template' => 'full-width']);
$type = $typeService->getById($typeId);
echo "  template={$type['template']}\n";
if ($type['template'] !== 'full-width') {
    echo "  ERRORE: update() del template non ha funzionato!\n";
    exit(1);
}

line("ContentEntryService::create() con template override valido -> deve passare");
$entryId = $entryService->create(['content_type_id' => $typeId, 'status_id' => $draft['id'], 'template' => 'case-study']);
$entry   = $entryService->getById($entryId);
echo "  entry template={$entry['template']}\n";

line("ContentEntryService::create() con template override NON valido -> deve fallire");
try {
    $entryService->create(['content_type_id' => $typeId, 'status_id' => $draft['id'], 'template' => 'inesistente']);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("ContentEntryService::update() con template override NON valido -> deve fallire, l'entry precedente resta intatta");
try {
    $entryService->update($entryId, ['template' => 'ancora-inesistente']);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}
$entry = $entryService->getById($entryId);
echo "  template dopo il tentativo fallito: {$entry['template']} (atteso invariato 'case-study')\n";
if ($entry['template'] !== 'case-study') {
    echo "  ERRORE: il template e' stato alterato nonostante l'eccezione!\n";
    exit(1);
}

line("ContentEntryService su Content Type SENZA template_options -> qualunque stringa passa");
$freeEntryId = $entryService->create(['content_type_id' => $freeTypeId, 'status_id' => $draft['id'], 'template' => 'davvero-qualunque-cosa']);
echo "  entry creata senza vincoli, id={$freeEntryId}\n";

line('done — tutti i controlli passati');
