<?php

/**
 * Smoke test del Contratto Theme↔CMS: Cms/ContentQuery/ContentEntryPresenter.
 * Mette insieme quasi tutti i pezzi della sessione dietro al contratto
 * pubblico: Content Type, Field (testo/traducibile/media/repeater),
 * Taxonomy, Lifecycle, Traduzioni — verificando che il tema veda solo
 * dati risolti, mai righe grezze o ID interni da interpretare.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Theme\Cms;
use Giga\Cms\Services\LanguageService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Services\ContentEntryTranslationService;
use Giga\Cms\Services\TaxonomyService;
use Giga\Cms\Services\TaxonomyTermService;
use Giga\Cms\Services\MediaService;
use Giga\Cms\Repositories\FieldRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$languageService     = new LanguageService();
$typeService         = new ContentTypeService();
$entryService        = new ContentEntryService();
$translationService  = new ContentEntryTranslationService();
$taxonomyService      = new TaxonomyService();
$termService          = new TaxonomyTermService();
$mediaService         = new MediaService();
$fieldRepo            = new FieldRepository();
$cms                  = new Cms();

try {
    $itId = $languageService->getByCode('it')['id'];
} catch (\RuntimeException) {
    $itId = $languageService->create(['code' => 'it', 'name' => 'Italiano', 'locale' => 'it_IT']);
}
try {
    $enId = $languageService->getByCode('en')['id'];
} catch (\RuntimeException) {
    $enId = $languageService->create(['code' => 'en', 'name' => 'English', 'locale' => 'en_US']);
}

line("Setup: Content Type 'showcase' + field testo/traducibile/media/repeater");
$typeId = $typeService->create(['slug' => 'showcase', 'label' => 'Showcase', 'label_singular' => 'Showcase']);

$clientFieldId    = $fieldRepo->create(['key' => 'client', 'label' => 'Cliente', 'type' => 'text']);
$descFieldId      = $fieldRepo->create(['key' => 'description', 'label' => 'Descrizione', 'type' => 'text', 'translatable' => true]);
$heroFieldId      = $fieldRepo->create(['key' => 'hero_image', 'label' => 'Immagine', 'type' => 'media']);
$highlightFieldId = $fieldRepo->create(['key' => 'highlight', 'label' => 'Punto forte', 'type' => 'text']);

line('Setup: Taxonomy industry + termine manufacturing, assegnata al Content Type');
$industryTaxId = $taxonomyService->create(['slug' => 'industry', 'label' => 'Industry', 'label_singular' => 'Industry']);
$manufacturingTermId = $termService->create(['taxonomy_id' => $industryTaxId, 'slug' => 'manufacturing', 'label' => 'Manufacturing']);
$typeService->syncTaxonomies($typeId, [$industryTaxId]);

line('Setup: media per hero_image');
$heroMediaId = $mediaService->create([
    'path' => '/uploads/hero.jpg', 'filename' => 'hero.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'size' => 5000, 'alt' => 'Hero',
]);

line("Entry A: pubblicata + featured, con tutti i field, tassonomia, traduzioni IT/EN");
$entryA = $entryService->create(['content_type_id' => $typeId, 'is_featured' => true]);
$entryService->publish($entryA);
$translationService->save($entryA, $itId, ['title' => 'Progetto Alfa', 'slug' => 'progetto-alfa']);
$translationService->save($entryA, $enId, ['title' => 'Alpha Project', 'slug' => 'alpha-project']);
$entryService->replaceValues($entryA, [
    ['field_id' => $clientFieldId, 'value_text' => 'Acme Spa'],
    ['field_id' => $descFieldId, 'language_id' => $itId, 'value_text' => 'Descrizione italiana'],
    ['field_id' => $descFieldId, 'language_id' => $enId, 'value_text' => 'English description'],
    ['field_id' => $heroFieldId, 'value_media_id' => $heroMediaId],
    ['field_id' => $highlightFieldId, 'sort_order' => 0, 'value_text' => 'Primo punto'],
    ['field_id' => $highlightFieldId, 'sort_order' => 1, 'value_text' => 'Secondo punto'],
]);
$entryService->syncTerms($entryA, $industryTaxId, [$manufacturingTermId]);

line('Entry B: pubblicata, NON featured, solo traduzione IT');
$entryB = $entryService->create(['content_type_id' => $typeId]);
$entryService->publish($entryB);
$translationService->save($entryB, $itId, ['title' => 'Progetto Beta', 'slug' => 'progetto-beta']);

line("Entry C: pubblicata ma SENZA traduzione IT (solo EN) -> deve sparire dalla query in IT (Policy no-fallback)");
$entryC = $entryService->create(['content_type_id' => $typeId]);
$entryService->publish($entryC);
$translationService->save($entryC, $enId, ['title' => 'Gamma Project', 'slug' => 'gamma-project']);

line('Entry D: draft -> non deve mai comparire in published()');
$entryD = $entryService->create(['content_type_id' => $typeId]);
$translationService->save($entryD, $itId, ['title' => 'Progetto Delta', 'slug' => 'progetto-delta']);

line("\$cms->content('showcase')->published()->get() -- lingua di default (it)");
$results = $cms->content('showcase')->published()->get();
foreach ($results as $r) {
    printf("  #%d %s (%s)\n", $r->id, $r->title, $r->slug);
}
$titles = array_map(fn($r) => $r->title, $results);
if (count($results) !== 2 || !in_array('Progetto Alfa', $titles, true) || !in_array('Progetto Beta', $titles, true)) {
    echo "  ERRORE: atteso esattamente Alfa+Beta (C senza traduzione IT, D draft)!\n";
    exit(1);
}

line("Conferma: Entry C (senza traduzione IT) e D (draft) NON compaiono, coerente con isPubliclyVisible()");
foreach ($results as $r) {
    if (in_array($r->title, ['Gamma Project', 'Progetto Delta'], true)) {
        echo "  ERRORE: {$r->title} non doveva comparire!\n";
        exit(1);
    }
}
echo "  ok\n";

line("->featured() restringe alla sola Entry A");
$featured = $cms->content('showcase')->published()->featured()->get();
echo '  risultati: ' . count($featured) . "\n";
if (count($featured) !== 1 || $featured[0]->title !== 'Progetto Alfa') {
    echo "  ERRORE: atteso solo Progetto Alfa!\n";
    exit(1);
}

line('->limit(1) rispettato, ->count() ignora il limit');
$limited = $cms->content('showcase')->published()->limit(1)->get();
$total   = $cms->content('showcase')->published()->count();
echo '  get() con limit(1): ' . count($limited) . ' risultati; count() totale: ' . $total . "\n";
if (count($limited) !== 1 || $total !== 2) {
    echo "  ERRORE: limit/count non si comportano come atteso!\n";
    exit(1);
}

line('Presenter: title/slug/template proprietà dirette, fields[] uniforme (Decisione #3)');
$alfa = $cms->content('showcase')->published()->featured()->first();
echo "  title={$alfa->title} slug={$alfa->slug}\n";
echo '  fields[client]: ' . $alfa->fields['client'] . "\n";
echo '  fields[description] (lingua IT): ' . $alfa->fields['description'] . "\n";
echo '  fields[hero_image]: ' . json_encode($alfa->fields['hero_image']) . "\n";
echo '  fields[highlight] (repeater): ' . json_encode($alfa->fields['highlight']) . "\n";
if ($alfa->fields['client'] !== 'Acme Spa') {
    echo "  ERRORE: field non traducibile non risolto correttamente!\n";
    exit(1);
}
if ($alfa->fields['description'] !== 'Descrizione italiana') {
    echo "  ERRORE: field traducibile non ha risolto la lingua corrente!\n";
    exit(1);
}
if (!is_array($alfa->fields['hero_image']) || $alfa->fields['hero_image']['path'] !== '/uploads/hero.jpg') {
    echo "  ERRORE: field media non risolto in descrittore arricchito!\n";
    exit(1);
}
if ($alfa->fields['highlight'] !== ['Primo punto', 'Secondo punto']) {
    echo "  ERRORE: field ripetuto non e' diventato un array ordinato!\n";
    exit(1);
}

line("Presenter: taxonomy('industry')");
$terms = $alfa->taxonomy('industry');
echo '  ' . json_encode($terms) . "\n";
if ($terms !== [['slug' => 'manufacturing', 'label' => 'Manufacturing']]) {
    echo "  ERRORE: taxonomy() non ha restituito il termine atteso!\n";
    exit(1);
}

line("Presenter: proprietà non esposta -> deve lanciare eccezione (niente accesso implicito a colonne interne)");
try {
    $alfa->status_id;
    echo "  ERRORE: nessuna eccezione, una colonna interna e' trapelata nel contratto pubblico!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("->taxonomy('industry', 'manufacturing') come filtro di query -> solo Entry A");
$byTaxonomy = $cms->content('showcase')->published()->taxonomy('industry', 'manufacturing')->get();
echo '  risultati: ' . count($byTaxonomy) . "\n";
if (count($byTaxonomy) !== 1 || $byTaxonomy[0]->title !== 'Progetto Alfa') {
    echo "  ERRORE: filtro per tassonomia non ha isolato la sola Entry A!\n";
    exit(1);
}

line("->taxonomy() con termine inesistente -> nessun risultato, nessuna eccezione");
$noResults = $cms->content('showcase')->published()->taxonomy('industry', 'inesistente')->get();
echo '  risultati: ' . count($noResults) . " (atteso 0)\n";
if (count($noResults) !== 0) {
    echo "  ERRORE: un termine inesistente doveva produrre zero risultati, non un errore o tutti i risultati!\n";
    exit(1);
}

line("->language('en') -> stessa Entry A, ora con title/slug/description in inglese");
$alfaEn = $cms->content('showcase')->published()->featured()->language('en')->first();
echo "  title={$alfaEn->title} slug={$alfaEn->slug} description={$alfaEn->fields['description']}\n";
if ($alfaEn->title !== 'Alpha Project' || $alfaEn->fields['description'] !== 'English description') {
    echo "  ERRORE: il cambio lingua non ha risolto i campi traducibili corretti!\n";
    exit(1);
}

line("Content Type inesistente -> deve fallire con un messaggio chiaro");
try {
    $cms->content('non-esiste');
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('done — Contratto Theme↔CMS verificato end-to-end');
