<?php

/**
 * Smoke test manuale di FieldService/FieldGroupService e delle due pivot
 * M:N (field_group_fields, content_type_field_groups), contro il DB
 * disposable di dev/.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\FieldService;
use Giga\Cms\Services\FieldGroupService;
use Giga\Cms\Services\ContentTypeService;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$fieldService = new FieldService();
$groupService = new FieldGroupService();
$typeService  = new ContentTypeService();

line("create() field con key riservata SQL-adjacent 'client_name' -> verifica quoting su `key`");
$clientFieldId = $fieldService->create([
    'key'   => 'client_name',
    'label' => 'Cliente',
    'type'  => 'text',
]);
$clientField = $fieldService->getById($clientFieldId);
echo '  ' . json_encode($clientField, JSON_UNESCAPED_SLASHES) . "\n";
if ($clientField['key'] !== 'client_name') {
    echo "  ERRORE: key non salvata correttamente!\n";
    exit(1);
}

line("update() sullo stesso field (verifica quoting anche in UPDATE)");
$fieldService->update($clientFieldId, ['label' => 'Nome Cliente']);
echo '  ' . json_encode($fieldService->getById($clientFieldId), JSON_UNESCAPED_SLASHES) . "\n";

line('create() field con config JSON (select con opzioni)');
$industryFieldId = $fieldService->create([
    'key'    => 'industry',
    'label'  => 'Settore',
    'type'   => 'select',
    'config' => ['options' => ['manufacturing', 'creative', 'retail']],
]);
$industryField = $fieldService->getById($industryFieldId);
echo '  config decodificato: ' . json_encode($industryField['config'], JSON_UNESCAPED_SLASHES) . "\n";
if (!is_array($industryField['config']) || $industryField['config']['options'][0] !== 'manufacturing') {
    echo "  ERRORE: config non decodificato correttamente!\n";
    exit(1);
}

line("create() field con key duplicata -> deve lanciare eccezione");
try {
    $fieldService->create(['key' => 'client_name', 'label' => 'Dup', 'type' => 'text']);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("create() field group 'project-details'");
$groupId = $groupService->create(['slug' => 'project-details', 'label' => 'Dettagli Progetto']);
echo "  creato field_group id={$groupId}\n";

line('syncFields() assegna client_name + industry al gruppo, in ordine');
$groupService->syncFields($groupId, [$clientFieldId, $industryFieldId]);
foreach ($groupService->getFields($groupId) as $f) {
    printf("  #%d key=%s sort_order=%d\n", $f['id'], $f['key'], $f['sort_order']);
}

line('vista inversa: a quali gruppi appartiene client_name?');
foreach ($fieldService->getFieldGroups($clientFieldId) as $g) {
    printf("  #%d slug=%s\n", $g['id'], $g['slug']);
}

line("create() secondo field group 'seo' che riusa lo stesso field 'industry' (prova riuso M:N)");
$seoGroupId = $groupService->create(['slug' => 'seo', 'label' => 'SEO']);
$groupService->syncFields($seoGroupId, [$industryFieldId]);
echo "  gruppi di 'industry' ora:\n";
foreach ($fieldService->getFieldGroups($industryFieldId) as $g) {
    printf("  #%d slug=%s\n", $g['id'], $g['slug']);
}
$industryGroups = $fieldService->getFieldGroups($industryFieldId);
if (count($industryGroups) !== 2) {
    echo "  ERRORE: atteso 'industry' in 2 gruppi, trovato " . count($industryGroups) . "\n";
    exit(1);
}

line("create() Content Type 'projects-fields-test' e assegna i due field group");
$typeId = $typeService->create([
    'slug'           => 'projects-fields-test',
    'label'          => 'Projects',
    'label_singular' => 'Project',
]);
$typeService->syncFieldGroups($typeId, [$groupId, $seoGroupId]);
foreach ($typeService->getFieldGroups($typeId) as $g) {
    printf("  content_type -> field_group #%d slug=%s sort_order=%d\n", $g['id'], $g['slug'], $g['sort_order']);
}

line('FK RESTRICT: provo a cancellare il field group ancora assegnato al Content Type -> deve fallire');
try {
    $groupService->delete($groupId);
    echo "  ERRORE: la delete e' passata, non doveva!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}

line("FK RESTRICT: provo a cancellare 'client_name' -> deve fallire (ancora nel field_group)");
try {
    $fieldService->delete($clientFieldId);
    echo "  ERRORE: la delete e' passata, non doveva!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}

line("Scioglimento pulito: rimuovo l'assegnazione, poi cancello field_group e field senza errori");
$typeService->syncFieldGroups($typeId, [$seoGroupId]);
$groupService->syncFields($groupId, []);
$groupService->delete($groupId);
$fieldService->delete($clientFieldId);
echo "  cancellazioni pulite completate senza eccezioni\n";

line('done — tutti i controlli passati');
