<?php

/**
 * Smoke test manuale di ContentEntryService, contro il DB disposable di
 * dev/. Presuppone che il Content Type "projects" esista già (creato da
 * smoke_test_repositories.php) — se non esiste, crealo prima con quello
 * script.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Services\ContentEntryService;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$typeRepo = new ContentTypeRepository();
$service  = new ContentEntryService();

$projectType = $typeRepo->findBySlug('projects');
if (!$projectType) {
    echo "Content Type 'projects' non trovato: esegui prima smoke_test_repositories.php\n";
    exit(1);
}

line('create() senza status_id esplicito -> default draft');
$entryId = $service->create(['content_type_slug' => 'projects']);
$entry   = $service->getById($entryId);
echo "  entry id={$entryId} status_key={$entry['status_key']}\n";
if ($entry['status_key'] !== 'draft') {
    echo "  ERRORE: atteso 'draft', ottenuto '{$entry['status_key']}'\n";
    exit(1);
}

line('replaceValues() con value_text/value_number -> deve passare');
$service->replaceValues($entryId, [
    ['field_id' => 201, 'value_text' => 'Cliente di prova'],
    ['field_id' => 202, 'value_number' => 42],
]);
echo '  values: ' . json_encode($service->getValues($entryId), JSON_UNESCAPED_SLASHES) . "\n";

line('replaceValues() con value_json valorizzato -> deve lanciare eccezione');
try {
    $service->replaceValues($entryId, [
        ['field_id' => 203, 'value_json' => json_encode(['a' => 1])],
    ]);
    echo "  ERRORE: nessuna eccezione lanciata, il blocco non funziona!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('conferma: i values precedenti non sono stati toccati dal tentativo fallito');
$values = $service->getValues($entryId);
echo '  numero di values presenti: ' . count($values) . " (atteso 2)\n";
if (count($values) !== 2) {
    echo "  ERRORE: i values sono stati alterati nonostante l'eccezione!\n";
    exit(1);
}

line('replaceValues() con value_json = null esplicito -> deve passare (chiave presente ma non valorizzata)');
$service->replaceValues($entryId, [
    ['field_id' => 204, 'value_text' => 'Altro campo', 'value_json' => null],
]);
echo '  values: ' . json_encode($service->getValues($entryId), JSON_UNESCAPED_SLASHES) . "\n";

line('create() senza content_type -> deve lanciare eccezione');
try {
    $service->create([]);
    echo "  ERRORE: nessuna eccezione lanciata!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('getById() su entry inesistente -> deve lanciare eccezione');
try {
    $service->getById(999999);
    echo "  ERRORE: nessuna eccezione lanciata!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('done — tutti i controlli passati');
