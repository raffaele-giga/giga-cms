<?php

/**
 * Smoke test manuale del Field Type registry: unità sui singoli tipi, poi
 * integrazione con ContentEntryService::replaceValues() per verificare la
 * validazione reale di value_json (sostituisce il blocco incondizionato).
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\FieldTypes\FieldTypeRegistry;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Services\ContentEntryService;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$registry  = new FieldTypeRegistry();
$fieldRepo = new FieldRepository();
$typeRepo  = new ContentTypeRepository();
$service   = new ContentEntryService();

line('Registry: tipi registrati e uses_value_json');
foreach (['text', 'richtext', 'number', 'date', 'datetime', 'boolean', 'select', 'relation', 'link'] as $key) {
    if (!$registry->has($key)) {
        echo "  ERRORE: '{$key}' non registrato!\n";
        exit(1);
    }
    $schema = $registry->get($key)->schema();
    printf("  %-10s uses_value_json=%s\n", $key, $schema['uses_value_json'] ? 'true' : 'false');
}
if ($registry->has('media')) {
    echo "  ERRORE: 'media' non dovrebbe essere registrato in questo giro!\n";
    exit(1);
}
echo "  conferma: 'media' correttamente NON registrato (Asset Library non ancora costruita)\n";

line('TextFieldType: validate + persist');
$text = $registry->get('text');
$text->validate('ciao', []);
echo '  persist: ' . json_encode($text->persist('ciao', [])) . "\n";
try {
    $text->validate(['non', 'stringa'], []);
    echo "  ERRORE: doveva rifiutare un array!\n";
    exit(1);
} catch (\InvalidArgumentException $e) {
    echo '  rifiutato correttamente: ' . $e->getMessage() . "\n";
}

line('NumberFieldType: min/max da config');
$number = $registry->get('number');
$number->validate(50, ['min' => 0, 'max' => 100]);
echo "  50 in [0,100]: OK\n";
try {
    $number->validate(150, ['min' => 0, 'max' => 100]);
    echo "  ERRORE: doveva rifiutare 150!\n";
    exit(1);
} catch (\InvalidArgumentException $e) {
    echo '  rifiutato correttamente: ' . $e->getMessage() . "\n";
}

line('SelectFieldType: opzioni da config');
$select = $registry->get('select');
$select->validate('creative', ['options' => ['manufacturing', 'creative', 'retail']]);
echo "  'creative' tra le opzioni: OK\n";
try {
    $select->validate('gaming', ['options' => ['manufacturing', 'creative', 'retail']]);
    echo "  ERRORE: doveva rifiutare 'gaming'!\n";
    exit(1);
} catch (\InvalidArgumentException $e) {
    echo '  rifiutato correttamente: ' . $e->getMessage() . "\n";
}

line("LinkFieldType: kind 'external' -> value_text + value_variant, niente value_json");
$link = $registry->get('link');
$link->validate(['kind' => 'external', 'value' => 'https://example.com'], []);
$persistedExternal = $link->persist(['kind' => 'external', 'value' => 'https://example.com'], []);
echo '  persist: ' . json_encode($persistedExternal) . "\n";
if (array_key_exists('value_json', $persistedExternal)) {
    echo "  ERRORE: non deve mai scrivere value_json!\n";
    exit(1);
}
echo '  render: ' . json_encode($link->render($persistedExternal, [])) . "\n";

line("LinkFieldType: kind 'entry' -> value_entry_id (stessa colonna/FK di RelationFieldType)");
$link->validate(['kind' => 'entry', 'value' => 42], []);
$persistedEntry = $link->persist(['kind' => 'entry', 'value' => 42], []);
echo '  persist: ' . json_encode($persistedEntry) . "\n";
if (!array_key_exists('value_entry_id', $persistedEntry) || array_key_exists('value_json', $persistedEntry)) {
    echo "  ERRORE: kind 'entry' deve usare value_entry_id, mai value_json!\n";
    exit(1);
}
echo '  render: ' . json_encode($link->render($persistedEntry, [])) . "\n";

line("LinkFieldType: kind 'file' -> deve fallire esplicitamente (Asset Library non ancora costruita)");
try {
    $link->validate(['kind' => 'file', 'value' => 1], []);
    echo "  ERRORE: doveva rifiutare kind='file'!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  rifiutato correttamente: ' . $e->getMessage() . "\n";
}

line("LinkFieldType: kind non valido -> deve fallire");
try {
    $link->validate(['kind' => 'bogus', 'value' => 'x'], []);
    echo "  ERRORE: doveva rifiutare kind='bogus'!\n";
    exit(1);
} catch (\InvalidArgumentException $e) {
    echo '  rifiutato correttamente: ' . $e->getMessage() . "\n";
}

line('Integrazione: setup Content Type + field text/link/media(non registrato)');
$typeId = $typeRepo->findBySlug('field-types-test')['id']
    ?? (new \Giga\Cms\Services\ContentTypeService())->create([
        'slug' => 'field-types-test', 'label' => 'FT Test', 'label_singular' => 'FT Test',
    ]);
$textFieldId  = $fieldRepo->create(['key' => 'ft_text', 'label' => 'Testo', 'type' => 'text']);
$linkFieldId  = $fieldRepo->create(['key' => 'ft_link', 'label' => 'CTA', 'type' => 'link']);
$mediaFieldId = $fieldRepo->create(['key' => 'ft_media', 'label' => 'Immagine', 'type' => 'media']);

$statusRepo  = new \Giga\Cms\Repositories\ContentStatusRepository();
$draftStatus = $statusRepo->findBySystemKey('draft');
$entryId     = (new \Giga\Cms\Repositories\ContentEntryRepository())->create([
    'content_type_id' => $typeId,
    'status_id'       => $draftStatus['id'],
]);

line("replaceValues: field 'text' con value_json valorizzato -> deve fallire (tipo registrato, non lo ammette)");
try {
    $service->replaceValues($entryId, [
        ['field_id' => $textFieldId, 'value_json' => json_encode(['x' => 1])],
    ]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("replaceValues: field 'link' kind 'external' via persist() -> value_text + value_variant, niente value_json");
$externalRow = array_merge(['field_id' => $linkFieldId], $link->persist(['kind' => 'external', 'value' => 'https://example.com'], []));
$service->replaceValues($entryId, [$externalRow]);
echo '  values: ' . json_encode($service->getValues($entryId), JSON_UNESCAPED_SLASHES) . "\n";

line("replaceValues: field 'link' kind 'entry' verso un'entry REALE -> deve passare, protetto da FK su value_entry_id");
$otherEntryId = (new \Giga\Cms\Repositories\ContentEntryRepository())->create([
    'content_type_id' => $typeId,
    'status_id'       => $draftStatus['id'],
]);
$entryRow = array_merge(['field_id' => $linkFieldId], $link->persist(['kind' => 'entry', 'value' => $otherEntryId], []));
$service->replaceValues($entryId, [$entryRow]);
echo '  values: ' . json_encode($service->getValues($entryId), JSON_UNESCAPED_SLASHES) . "\n";

line("replaceValues: field 'link' kind 'entry' verso un'entry INESISTENTE -> deve fallire (FK, non JSON senza protezione)");
$bogusRow = array_merge(['field_id' => $linkFieldId], $link->persist(['kind' => 'entry', 'value' => 999999], []));
try {
    $service->replaceValues($entryId, [$bogusRow]);
    echo "  ERRORE: nessuna eccezione, la FK non ha protetto il riferimento!\n";
    exit(1);
} catch (\PDOException $e) {
    echo '  bloccato correttamente dalla FK: ' . $e->getMessage() . "\n";
}

line("replaceValues: field 'media' (type non registrato) con value_json valorizzato -> deve fallire (restrizione conservativa)");
try {
    $service->replaceValues($entryId, [
        ['field_id' => $mediaFieldId, 'value_json' => json_encode(['x' => 1])],
    ]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("replaceValues: field 'text' con value_text normale -> deve passare senza toccare la validazione value_json");
$service->replaceValues($entryId, [
    ['field_id' => $textFieldId, 'value_text' => 'valore normale'],
]);
echo '  values: ' . json_encode($service->getValues($entryId), JSON_UNESCAPED_SLASHES) . "\n";

line('done — tutti i controlli passati');
