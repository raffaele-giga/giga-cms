<?php

/**
 * Smoke test di Menu (menus/menu_items, gerarchia, target) e Page Builder
 * (block_types, istanze di blocco su un'entry, valori scoped per
 * block_id + language_id, esposizione via Cms/ContentEntryPresenter).
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Theme\Cms;
use Giga\Cms\Services\LanguageService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Services\ContentEntryTranslationService;
use Giga\Cms\Services\MenuService;
use Giga\Cms\Services\MenuItemService;
use Giga\Cms\Services\BlockTypeService;
use Giga\Cms\Services\FieldGroupService;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\ContentEntryRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$languageService     = new LanguageService();
$typeService         = new ContentTypeService();
$entryService        = new ContentEntryService();
$translationService  = new ContentEntryTranslationService();
$menuService         = new MenuService();
$menuItemService     = new MenuItemService();
$blockTypeService    = new BlockTypeService();
$fieldGroupService   = new FieldGroupService();
$fieldRepo           = new FieldRepository();
$statusRepo          = new ContentStatusRepository();
$entryRepo           = new ContentEntryRepository();
$cms                 = new Cms();

try {
    $itId = $languageService->getByCode('it')['id'];
} catch (\RuntimeException) {
    $itId = $languageService->create(['code' => 'it', 'name' => 'Italiano', 'locale' => 'it_IT']);
}

// ============================================================
// MENU
// ============================================================

line("Setup: Content Type 'page' (sistema) + due entry pubblicate");
$pageTypeId = $typeService->create(['slug' => 'page', 'label' => 'Pages', 'label_singular' => 'Page', 'is_system' => true]);
$draft      = $statusRepo->findBySystemKey('draft');

$homeId = $entryRepo->create(['content_type_id' => $pageTypeId, 'status_id' => $draft['id']]);
$entryService->publish($homeId);
$translationService->save($homeId, $itId, ['title' => 'Home', 'slug' => 'home']);

$aboutId = $entryRepo->create(['content_type_id' => $pageTypeId, 'status_id' => $draft['id']]);
$entryService->publish($aboutId);
$translationService->save($aboutId, $itId, ['title' => 'Chi siamo', 'slug' => 'chi-siamo']);

// Entry senza traduzione IT, per il caso "link rotto"
$noTranslationId = $entryRepo->create(['content_type_id' => $pageTypeId, 'status_id' => $draft['id']]);
$entryService->publish($noTranslationId);

line("create() menu 'main-nav' con gerarchia: Home, Chi siamo, Link esterno, Sezione (con figlio Prodotti -> anchor)");
$menuId = $menuService->create(['slug' => 'main-nav', 'label' => 'Main Navigation']);

$homeItemId  = $menuItemService->create(['menu_id' => $menuId, 'label' => 'Home', 'target_variant' => 'entry', 'target_entry_id' => $homeId, 'sort_order' => 0]);
$aboutItemId = $menuItemService->create(['menu_id' => $menuId, 'label' => 'Chi siamo', 'target_variant' => 'entry', 'target_entry_id' => $aboutId, 'sort_order' => 1]);
$extItemId   = $menuItemService->create(['menu_id' => $menuId, 'label' => 'Sito esterno', 'target_variant' => 'url', 'target_url' => 'https://example.com', 'sort_order' => 2]);
$sectionId   = $menuItemService->create(['menu_id' => $menuId, 'label' => 'Sezione', 'target_variant' => 'anchor', 'target_url' => '#sezione', 'sort_order' => 3]);
$childId     = $menuItemService->create(['menu_id' => $menuId, 'label' => 'Sotto-voce', 'parent_id' => $sectionId, 'target_variant' => 'anchor', 'target_url' => '#sotto', 'sort_order' => 0]);
$brokenId    = $menuItemService->create(['menu_id' => $menuId, 'label' => 'Rotto', 'target_variant' => 'entry', 'target_entry_id' => $noTranslationId, 'sort_order' => 4]);

line("target_variant 'entry' senza target_entry_id -> deve fallire");
try {
    $menuItemService->create(['menu_id' => $menuId, 'label' => 'X', 'target_variant' => 'entry']);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("target_variant 'url' senza target_url -> deve fallire");
try {
    $menuItemService->create(['menu_id' => $menuId, 'label' => 'X', 'target_variant' => 'url']);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("update() prova a rendere 'Sezione' figlio di 'Sotto-voce' -> deve fallire (ciclo)");
try {
    $menuItemService->update($sectionId, ['parent_id' => $childId]);
    echo "  ERRORE: nessuna eccezione, ciclo non rilevato!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("Cms::menu('main-nav') -- albero risolto, url pronti per il rendering");
$tree = $cms->menu('main-nav');
echo json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($tree[0]['label'] !== 'Home' || $tree[0]['url'] !== '/it/page/home') {
    echo "  ERRORE: url della Home non risolto correttamente!\n";
    exit(1);
}
if ($tree[3]['label'] !== 'Sezione' || count($tree[3]['children']) !== 1 || $tree[3]['children'][0]['label'] !== 'Sotto-voce') {
    echo "  ERRORE: gerarchia annidata non ricostruita correttamente!\n";
    exit(1);
}
if ($tree[4]['url'] !== null) {
    echo "  ERRORE: il link verso un'entry senza traduzione doveva risolvere a url=null, non fallire l'intero menu!\n";
    exit(1);
}
echo "  ok: gerarchia, url risolti, link rotto gestito senza eccezioni\n";

// ============================================================
// PAGE BUILDER
// ============================================================

line("Setup: Field Group 'hero-fields' con field heading (traducibile) e cta_label");
$headingFieldId  = $fieldRepo->create(['key' => 'heading', 'label' => 'Titolo', 'type' => 'text', 'translatable' => true]);
$ctaFieldId      = $fieldRepo->create(['key' => 'cta_label', 'label' => 'Testo CTA', 'type' => 'text']);
$heroGroupId     = $fieldGroupService->create(['slug' => 'hero-fields', 'label' => 'Hero Fields']);
$fieldGroupService->syncFields($heroGroupId, [$headingFieldId, $ctaFieldId]);

line("create() Block Type 'hero' con Field Group assegnato");
$heroBlockTypeId = $blockTypeService->create(['slug' => 'hero', 'label' => 'Hero']);
$blockTypeService->syncFieldGroups($heroBlockTypeId, [$heroGroupId]);
echo '  field groups del block type: ' . json_encode(array_column($blockTypeService->getFieldGroups($heroBlockTypeId), 'slug')) . "\n";

line("addBlock(): 2 istanze di 'hero' sulla Home, in ordine");
$block1Id = $entryService->addBlock($homeId, $heroBlockTypeId);
$block2Id = $entryService->addBlock($homeId, $heroBlockTypeId);
foreach ($entryService->getBlocks($homeId) as $b) {
    printf("  block #%d type=%s sort=%d\n", $b['id'], $b['block_type_slug'], $b['sort_order']);
}

line("replaceBlockValues(): heading (traducibile) SENZA language_id -> deve fallire, stessa validazione di replaceValues");
try {
    $entryService->replaceBlockValues($block1Id, [['field_id' => $headingFieldId, 'value_text' => 'Senza lingua']]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("replaceBlockValues(): block1 heading='Primo blocco' (IT), block2 heading='Secondo blocco' (IT) -- stesso field_id, block_id diversi");
$entryService->replaceBlockValues($block1Id, [
    ['field_id' => $headingFieldId, 'language_id' => $itId, 'value_text' => 'Primo blocco'],
    ['field_id' => $ctaFieldId, 'value_text' => 'Scopri di più'],
]);
$entryService->replaceBlockValues($block2Id, [
    ['field_id' => $headingFieldId, 'language_id' => $itId, 'value_text' => 'Secondo blocco'],
]);

line('Conferma: i values dei due blocchi NON si mescolano (stesso field_id, block_id diverso)');
$values1 = $entryService->getBlockValues($block1Id);
$values2 = $entryService->getBlockValues($block2Id);
echo '  block1: ' . json_encode(array_map(fn($v) => $v['value_text'], $values1)) . "\n";
echo '  block2: ' . json_encode(array_map(fn($v) => $v['value_text'], $values2)) . "\n";
if (count($values1) !== 2 || count($values2) !== 1) {
    echo "  ERRORE: scoping per block_id non isola correttamente i values dei due blocchi!\n";
    exit(1);
}

line("Conferma: i values di entry-level (getValues) NON includono quelli dei blocchi, e viceversa");
$directValues = $entryService->getValues($homeId);
echo '  values diretti della Home: ' . count($directValues) . " (atteso 0, nessun field diretto impostato)\n";
if (count($directValues) !== 0) {
    echo "  ERRORE: getValues() ha restituito anche values scoped a un blocco!\n";
    exit(1);
}

line('reorderBlocks(): inverto block2, block1 -- deve aggiornare sort_order sulle righe esistenti, non ricrearle');
$entryService->reorderBlocks($homeId, [$block2Id, $block1Id]);
$blocksAfterReorder = $entryService->getBlocks($homeId);
echo '  ordine dopo reorder: ' . json_encode(array_column($blocksAfterReorder, 'id')) . "\n";
if ($blocksAfterReorder[0]['id'] !== $block2Id || $blocksAfterReorder[1]['id'] !== $block1Id) {
    echo "  ERRORE: reorder non ha funzionato come atteso!\n";
    exit(1);
}
echo '  values di block1 sopravvissuti al reorder: ' . count($entryService->getBlockValues($block1Id)) . " (atteso 2, stesso id, non ricreato)\n";
if (count($entryService->getBlockValues($block1Id)) !== 2) {
    echo "  ERRORE: il reorder ha distrutto i values invece di limitarsi a riordinare!\n";
    exit(1);
}

line('removeBlock(): cancello block2 -- CASCADE ripulisce i suoi values, block1 resta intatto');
$entryService->removeBlock($block2Id);
$remainingBlocks = $entryService->getBlocks($homeId);
echo '  blocchi rimasti: ' . json_encode(array_column($remainingBlocks, 'id')) . "\n";
if (count($remainingBlocks) !== 1 || $remainingBlocks[0]['id'] !== $block1Id) {
    echo "  ERRORE: removeBlock ha rimosso il blocco sbagliato o non ha rimosso nulla!\n";
    exit(1);
}

line("Contratto Theme↔CMS: \$page->blocks() espone type+fields, block cancellato non compare");
$homePresenter = $cms->content('page')->published()->first();
foreach ($homePresenter->blocks() as $block) {
    echo "  block type={$block->type} fields=" . json_encode($block->fields) . "\n";
}
$blocks = $homePresenter->blocks();
if (count($blocks) !== 1 || $blocks[0]->type !== 'hero' || $blocks[0]->fields['heading'] !== 'Primo blocco') {
    echo "  ERRORE: il Presenter non espone i blocchi correttamente!\n";
    exit(1);
}

line('FK RESTRICT: provo a cancellare il Block Type ancora usato da un\'istanza -> deve fallire');
try {
    $blockTypeService->delete($heroBlockTypeId);
    echo "  ERRORE: la delete e' passata, non doveva!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}

line('done — tutti i controlli passati');
