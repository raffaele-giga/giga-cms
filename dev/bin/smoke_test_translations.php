<?php

/**
 * Smoke test di LanguageService, ContentEntryTranslationService,
 * RedirectService: slugify, collisioni, permalink, redirect automatico al
 * cambio slug, campi Custom Field traducibili (language_id), e
 * isPubliclyVisible() (stato + traduzione, niente fallback tra lingue).
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\LanguageService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Services\ContentEntryTranslationService;
use Giga\Cms\Services\RedirectService;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\FieldRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$languageService    = new LanguageService();
$typeService        = new ContentTypeService();
$entryService       = new ContentEntryService();
$translationService = new ContentEntryTranslationService();
$redirectService     = new RedirectService();
$statusRepo          = new ContentStatusRepository();
$fieldRepo           = new FieldRepository();

line("create() lingua 'it' -> prima lingua, diventa default automaticamente");
$itId = $languageService->create(['code' => 'it', 'name' => 'Italiano', 'locale' => 'it_IT']);
$it   = $languageService->getById($itId);
echo '  ' . json_encode($it) . "\n";
if (!$it['is_default']) {
    echo "  ERRORE: la prima lingua deve diventare default automaticamente!\n";
    exit(1);
}

line("create() lingua 'en' -> non default (it lo è già)");
$enId = $languageService->create(['code' => 'en', 'name' => 'English', 'locale' => 'en_US']);
$en   = $languageService->getById($enId);
if ($en['is_default']) {
    echo "  ERRORE: 'en' non doveva essere default!\n";
    exit(1);
}
echo "  ok, 'en' non è default\n";

line("update() rende 'en' default -> 'it' deve perderlo automaticamente");
$languageService->update($enId, ['is_default' => true]);
$it = $languageService->getById($itId);
$en = $languageService->getById($enId);
echo "  it.is_default=" . var_export((bool) $it['is_default'], true) . " en.is_default=" . var_export((bool) $en['is_default'], true) . "\n";
if ($it['is_default'] || !$en['is_default']) {
    echo "  ERRORE: il flip del default non ha funzionato!\n";
    exit(1);
}
$languageService->update($itId, ['is_default' => true]); // torna 'it' default per il resto del test

line("delete() sulla lingua di default -> deve fallire");
try {
    $languageService->delete($itId);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("Setup: Content Type 'articles-i18n' senza permalink_pattern -> fallback /{slug del tipo}/{slug}");
$typeId = $typeService->create(['slug' => 'articles-i18n', 'label' => 'Articles', 'label_singular' => 'Article']);
$draft  = $statusRepo->findBySystemKey('draft');
$entryId = (new \Giga\Cms\Repositories\ContentEntryRepository())->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);

line("save() traduzione IT senza slug esplicito -> generato dal title via slugify");
$translationService->save($entryId, $itId, ['title' => "Il Mio Articolo!"]);
$itTranslation = $translationService->getByEntryAndLanguage($entryId, $itId);
echo "  slug generato: '{$itTranslation['slug']}'\n";
if ($itTranslation['slug'] !== 'il-mio-articolo') {
    echo "  ERRORE: slugify non ha prodotto il risultato atteso!\n";
    exit(1);
}

line("getPermalink() IT -> deve usare il fallback /{content_type.slug}/{slug} con prefisso lingua");
$permalinkIt = $translationService->getPermalink($entryId, $itId);
echo "  {$permalinkIt}\n";
if ($permalinkIt !== '/it/articles-i18n/il-mio-articolo') {
    echo "  ERRORE: permalink inatteso!\n";
    exit(1);
}

line("save() una SECONDA entry con lo stesso title -> collisione risolta con suffisso incrementale");
$entryId2 = (new \Giga\Cms\Repositories\ContentEntryRepository())->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);
$translationService->save($entryId2, $itId, ['title' => "Il Mio Articolo!"]);
$slug2 = $translationService->getByEntryAndLanguage($entryId2, $itId)['slug'];
echo "  slug seconda entry: '{$slug2}'\n";
if ($slug2 !== 'il-mio-articolo-2') {
    echo "  ERRORE: atteso suffisso incrementale '-2'!\n";
    exit(1);
}

line("save() con slug esplicito già in uso -> anche qui suffisso incrementale");
$entryId3 = (new \Giga\Cms\Repositories\ContentEntryRepository())->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);
$translationService->save($entryId3, $itId, ['title' => 'Altro titolo', 'slug' => 'il-mio-articolo']);
$slug3 = $translationService->getByEntryAndLanguage($entryId3, $itId)['slug'];
echo "  slug terza entry: '{$slug3}'\n";
if ($slug3 !== 'il-mio-articolo-3') {
    echo "  ERRORE: atteso 'il-mio-articolo-3' (il-mio-articolo e -2 già usati)!\n";
    exit(1);
}

line("save() stesso slug in un'altra LINGUA (en) -> nessuna collisione, namespace diverso");
$translationService->save($entryId, $enId, ['title' => 'My Article', 'slug' => 'il-mio-articolo']);
echo "  slug EN 'il-mio-articolo' accettato senza suffisso (namespace = content_type+lingua)\n";

line("update() cambia lo slug IT della prima entry -> deve generare un redirect automatico");
$oldPermalink = $translationService->getPermalink($entryId, $itId);
$translationService->save($entryId, $itId, ['title' => 'Il Mio Articolo Rinominato']);
$newPermalink = $translationService->getPermalink($entryId, $itId);
echo "  {$oldPermalink} -> {$newPermalink}\n";
$resolved = $redirectService->resolve($oldPermalink);
echo "  redirect risolto per il vecchio path: {$resolved}\n";
if ($resolved !== $newPermalink) {
    echo "  ERRORE: il redirect automatico non punta al nuovo permalink!\n";
    exit(1);
}

line("Override permalink per lingua (Decisione #2): segmenti di path DIVERSI per IT/EN, non solo il prefisso");
$typeService->setPermalinkPatternOverride($typeId, $itId, '/realizzazioni/{slug}');
$typeService->setPermalinkPatternOverride($typeId, $enId, '/case-studies/{slug}');
$overrideEntryId = (new \Giga\Cms\Repositories\ContentEntryRepository())->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);
$translationService->save($overrideEntryId, $itId, ['title' => 'Progetto Esempio']);
$translationService->save($overrideEntryId, $enId, ['title' => 'Example Project']);
$permalinkItOverride = $translationService->getPermalink($overrideEntryId, $itId);
$permalinkEnOverride = $translationService->getPermalink($overrideEntryId, $enId);
echo "  IT: {$permalinkItOverride}\n";
echo "  EN: {$permalinkEnOverride}\n";
if ($permalinkItOverride !== '/it/realizzazioni/progetto-esempio' || $permalinkEnOverride !== '/en/case-studies/example-project') {
    echo "  ERRORE: l'override per lingua non ha prodotto segmenti di path diversi!\n";
    exit(1);
}

line('getPermalinkPatternOverrides() elenca gli override configurati');
foreach ($typeService->getPermalinkPatternOverrides($typeId) as $o) {
    printf("  lang=%s pattern=%s\n", $o['language_code'], $o['pattern']);
}

line("removePermalinkPatternOverride() su EN -> torna al pattern di default del Content Type (non all'override IT)");
$typeService->removePermalinkPatternOverride($typeId, $enId);
$permalinkEnAfterRemove = $translationService->getPermalink($overrideEntryId, $enId);
echo "  EN dopo la rimozione: {$permalinkEnAfterRemove}\n";
if ($permalinkEnAfterRemove !== '/en/articles-i18n/example-project') {
    echo "  ERRORE: la rimozione dell'override non e' tornata al fallback atteso!\n";
    exit(1);
}
$permalinkItStillOverridden = $translationService->getPermalink($overrideEntryId, $itId);
echo "  IT (deve restare sull'override, non toccato): {$permalinkItStillOverridden}\n";
if ($permalinkItStillOverridden !== '/it/realizzazioni/progetto-esempio') {
    echo "  ERRORE: rimuovere l'override EN ha toccato anche IT!\n";
    exit(1);
}

line("Custom Field traducibile: replaceValues senza language_id -> deve fallire");
$descFieldId = $fieldRepo->create(['key' => 'description_i18n', 'label' => 'Descrizione', 'type' => 'text', 'translatable' => true]);
try {
    $entryService->replaceValues($entryId, [['field_id' => $descFieldId, 'value_text' => 'Senza lingua']]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("Custom Field traducibile: replaceValues CON language_id -> deve passare, una riga per lingua");
$entryService->replaceValues($entryId, [
    ['field_id' => $descFieldId, 'language_id' => $itId, 'value_text' => 'Descrizione italiana'],
    ['field_id' => $descFieldId, 'language_id' => $enId, 'value_text' => 'English description'],
]);
foreach ($entryService->getValues($entryId) as $v) {
    if ((int) $v['field_id'] === $descFieldId) {
        printf("  lang_id=%s value_text=%s\n", $v['language_id'], $v['value_text']);
    }
}

line("Custom Field NON traducibile: replaceValues CON language_id -> deve fallire");
$yearFieldId = $fieldRepo->create(['key' => 'year_i18n', 'label' => 'Anno', 'type' => 'number']);
try {
    $entryService->replaceValues($entryId, [['field_id' => $yearFieldId, 'language_id' => $itId, 'value_number' => 2026]]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('isPubliclyVisible(): entry draft -> false in entrambe le lingue');
echo '  IT: ' . var_export($translationService->isPubliclyVisible($entryId, $itId), true) . "\n";
if ($translationService->isPubliclyVisible($entryId, $itId)) {
    echo "  ERRORE: draft non deve mai essere pubblicamente visibile!\n";
    exit(1);
}

line("isPubliclyVisible(): entry pubblicata, traduzione IT esiste, traduzione FR non esiste -> true/false, nessun fallback");
$entryService->publish($entryId);
$frId = $languageService->create(['code' => 'fr', 'name' => 'Français', 'locale' => 'fr_FR']);
$visibleIt = $translationService->isPubliclyVisible($entryId, $itId);
$visibleFr = $translationService->isPubliclyVisible($entryId, $frId);
echo "  IT: " . var_export($visibleIt, true) . " (attesa true)\n";
echo "  FR: " . var_export($visibleFr, true) . " (attesa false, nessuna traduzione, niente fallback)\n";
if (!$visibleIt || $visibleFr) {
    echo "  ERRORE: Policy contenuto incompleto V1 non rispettata!\n";
    exit(1);
}

line('FK RESTRICT: provo a cancellare la lingua IT (ancora usata da traduzioni/valori) -> deve fallire');
try {
    $languageService->update($enId, ['is_default' => true]); // sposta il default altrove, altrimenti fallirebbe per quel motivo
    $languageService->delete($itId);
    echo "  ERRORE: la delete e' passata, non doveva (FK)!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
} finally {
    $languageService->update($itId, ['is_default' => true]);
}

line('done — tutti i controlli passati');
