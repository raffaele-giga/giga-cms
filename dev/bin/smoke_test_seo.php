<?php

/**
 * Smoke test SEO: meta title/description/canonical con fallback, robots
 * meta (indexable+follow), OG tags con fallback, json_ld_type. Verifica
 * anche permalink_pattern/json_ld_type impostabili da ContentTypeService
 * (bug trovato durante questo stesso giro: permalink_pattern non era mai
 * stato aggiunto all'allowlist di create()/update()).
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\LanguageService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Services\ContentEntryTranslationService;
use Giga\Cms\Services\MediaService;
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
$mediaService        = new MediaService();
$statusRepo          = new ContentStatusRepository();
$entryRepo           = new ContentEntryRepository();

try {
    $itId = $languageService->getByCode('it')['id'];
} catch (\RuntimeException) {
    $itId = $languageService->create(['code' => 'it', 'name' => 'Italiano', 'locale' => 'it_IT']);
}

line("create() Content Type con permalink_pattern e json_ld_type -> verifica che ora si impostino davvero");
$typeId = $typeService->create([
    'slug' => 'seo-test', 'label' => 'SEO Test', 'label_singular' => 'SEO Test',
    'permalink_pattern' => '/blog/{slug}',
    'json_ld_type' => 'Article',
]);
$type = $typeService->getById($typeId);
echo "  permalink_pattern={$type['permalink_pattern']} json_ld_type={$type['json_ld_type']}\n";
if ($type['permalink_pattern'] !== '/blog/{slug}' || $type['json_ld_type'] !== 'Article') {
    echo "  ERRORE: permalink_pattern/json_ld_type non impostati da create()!\n";
    exit(1);
}

line("update() cambia solo json_ld_type -> permalink_pattern deve restare invariato");
$typeService->update($typeId, ['json_ld_type' => 'BlogPosting']);
$type = $typeService->getById($typeId);
echo "  permalink_pattern={$type['permalink_pattern']} json_ld_type={$type['json_ld_type']}\n";
if ($type['permalink_pattern'] !== '/blog/{slug}' || $type['json_ld_type'] !== 'BlogPosting') {
    echo "  ERRORE: update() ha toccato un campo che non doveva!\n";
    exit(1);
}

$draft   = $statusRepo->findBySystemKey('draft');
$entryId = $entryRepo->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);
$translationService->save($entryId, $itId, ['title' => 'Il Mio Post']);

line('getSeoMeta() SENZA override -> tutto in fallback');
$seo = $translationService->getSeoMeta($entryId, $itId);
echo '  ' . json_encode($seo, JSON_UNESCAPED_SLASHES) . "\n";
if ($seo['meta_title'] !== 'Il Mio Post') {
    echo "  ERRORE: meta_title doveva ricadere sul title!\n";
    exit(1);
}
if ($seo['canonical_url'] !== '/it/blog/il-mio-post') {
    echo "  ERRORE: canonical_url doveva ricadere sul permalink calcolato!\n";
    exit(1);
}
if ($seo['robots'] !== 'index,follow') {
    echo "  ERRORE: robots di default doveva essere 'index,follow'!\n";
    exit(1);
}
if ($seo['og_title'] !== 'Il Mio Post' || $seo['og_description'] !== null || $seo['og_media_id'] !== null) {
    echo "  ERRORE: OG doveva ricadere su meta_title/null!\n";
    exit(1);
}
if ($seo['json_ld_type'] !== 'BlogPosting') {
    echo "  ERRORE: json_ld_type doveva venire dal Content Type!\n";
    exit(1);
}

line('Media per og_media_id, poi save() con override SEO espliciti');
$mediaId = $mediaService->create([
    'path' => '/uploads/og.jpg', 'filename' => 'og.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'size' => 1000,
]);
$translationService->save($entryId, $itId, [
    'title'             => 'Il Mio Post',
    'meta_title'        => 'Titolo SEO personalizzato',
    'meta_description'  => 'Descrizione SEO personalizzata',
    'canonical_url'     => '/altra-pagina-canonica',
    'og_title'          => 'Titolo OG personalizzato',
    'og_description'    => 'Descrizione OG personalizzata',
    'og_media_id'       => $mediaId,
]);
$seo = $translationService->getSeoMeta($entryId, $itId);
echo '  ' . json_encode($seo, JSON_UNESCAPED_SLASHES) . "\n";
if ($seo['meta_title'] !== 'Titolo SEO personalizzato' || $seo['canonical_url'] !== '/altra-pagina-canonica' || $seo['og_media_id'] !== $mediaId) {
    echo "  ERRORE: gli override espliciti non hanno vinto sul fallback!\n";
    exit(1);
}

line("update() entry indexable=false, follow=false -> robots='noindex,nofollow'");
$entryService->update($entryId, ['indexable' => false, 'follow' => false]);
$seo = $translationService->getSeoMeta($entryId, $itId);
echo "  robots={$seo['robots']}\n";
if ($seo['robots'] !== 'noindex,nofollow') {
    echo "  ERRORE: robots atteso 'noindex,nofollow'!\n";
    exit(1);
}

line("update() entry indexable=true, follow=false -> robots='index,nofollow' (combinazione non citata ma non esclusa)");
$entryService->update($entryId, ['indexable' => true, 'follow' => false]);
$seo = $translationService->getSeoMeta($entryId, $itId);
echo "  robots={$seo['robots']}\n";
if ($seo['robots'] !== 'index,nofollow') {
    echo "  ERRORE: robots atteso 'index,nofollow'!\n";
    exit(1);
}

line('SET NULL: cancello il media usato come og_media_id -> il campo diventa NULL, non blocca la cancellazione');
$mediaService->delete($mediaId);
$seo = $translationService->getSeoMeta($entryId, $itId);
echo '  og_media_id dopo la cancellazione: ' . var_export($seo['og_media_id'], true) . "\n";
if ($seo['og_media_id'] !== null) {
    echo "  ERRORE: doveva diventare NULL (SET NULL)!\n";
    exit(1);
}

line('done — tutti i controlli passati');
