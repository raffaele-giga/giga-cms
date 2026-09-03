<?php

/**
 * Smoke test/recipe della Content Type "News" (roadmap punto 10, primo
 * consumer reale cms-neviobianchi). Non c'è alcun dato legacy da
 * migrare (le news esistenti sono segnaposto) — questo script verifica
 * solo che la composizione Content Type + Field Group funzioni end-to-
 * end, ed è la stessa sequenza di chiamate che verrà rieseguita per
 * davvero contro il DB reale una volta collegato cms-neviobianchi via
 * Composer (step successivo della roadmap).
 *
 * Mapping dallo schema legacy (news/news_documents) ai primitivi del
 * Content Engine:
 *  - title/slug          -> content_entry_translations (built-in)
 *  - content             -> Custom Field 'body' (richtext, traducibile)
 *  - status/published_at -> content_entries/content_statuses (built-in)
 *  - image (singola)     -> Custom Field 'image' (media, non traducibile)
 *  - news_documents      -> Gallery (supports_media, non un Custom Field)
 *  - seo_title/seo_desc  -> content_entry_translations.meta_* (supports_seo)
 *  - visit_count         -> content_entry_stats (supports_stats)
 * Esplicitamente FUORI da questa Content Type, per direttiva:
 *  - linkedin_published/linkedin_published_at: estensione locale di
 *    cms-neviobianchi, non una feature del Content Engine.
 *  - created_by: nessun dato da migrare la rende necessaria per V1;
 *    omesso, non un'omissione silenziosa (nessun equivalente esiste
 *    oggi in content_entries).
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Theme\Cms;
use Giga\Cms\Services\LanguageService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Services\ContentEntryTranslationService;
use Giga\Cms\Services\FieldGroupService;
use Giga\Cms\Repositories\FieldRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$languageService     = new LanguageService();
$typeService         = new ContentTypeService();
$entryService        = new ContentEntryService();
$translationService  = new ContentEntryTranslationService();
$fieldGroupService   = new FieldGroupService();
$fieldRepo           = new FieldRepository();
$cms                 = new Cms();

try {
    $itId = $languageService->getByCode('it')['id'];
} catch (\RuntimeException) {
    $itId = $languageService->create(['code' => 'it', 'name' => 'Italiano', 'locale' => 'it_IT']);
}

line("create() Content Type 'news'");
$newsTypeId = $typeService->create([
    'slug'              => 'news',
    'label'             => 'News',
    'label_singular'    => 'Notizia',
    'is_system'         => false,
    'supports_archive'  => true,
    'supports_seo'      => true,
    'supports_media'    => true,
    'supports_featured' => true,
    'supports_stats'    => true,
    'default_ordering'  => 'published_at',
    'permalink_pattern' => '/news/{slug}',
    'json_ld_type'      => 'NewsArticle',
]);
$newsType = $typeService->getById($newsTypeId);
printf(
    "  supports: archive=%d seo=%d media=%d featured=%d stats=%d\n",
    $newsType['supports_archive'], $newsType['supports_seo'], $newsType['supports_media'],
    $newsType['supports_featured'], $newsType['supports_stats']
);
if (!$newsType['supports_stats']) {
    echo "  ERRORE: 'News' deve nascere con supports_stats=true!\n";
    exit(1);
}

line("Field Group 'news-content': body (richtext, traducibile) + image (media)");
$bodyFieldId  = $fieldRepo->create(['key' => 'body', 'label' => 'Corpo', 'type' => 'richtext', 'translatable' => true]);
$imageFieldId = $fieldRepo->create(['key' => 'image', 'label' => 'Immagine', 'type' => 'media']);
$groupId      = $fieldGroupService->create(['slug' => 'news-content', 'label' => 'Contenuto News']);
$fieldGroupService->syncFields($groupId, [$bodyFieldId, $imageFieldId]);
$typeService->syncFieldGroups($newsTypeId, [$groupId]);

$assignedGroups = $typeService->getFieldGroups($newsTypeId);
echo '  field groups assegnati: ' . json_encode(array_column($assignedGroups, 'slug')) . "\n";
if (count($assignedGroups) !== 1 || $assignedGroups[0]['slug'] !== 'news-content') {
    echo "  ERRORE: Field Group non assegnato correttamente!\n";
    exit(1);
}

line('Entry di prova: tutti i campi popolati, pubblicata, con SEO e featured');
$entryId = $entryService->create(['content_type_id' => $newsTypeId, 'is_featured' => true]);
$entryService->publish($entryId);
$translationService->save($entryId, $itId, [
    'title'           => 'Nuova sede a Milano',
    'slug'            => 'nuova-sede-a-milano',
    'meta_title'      => 'Nuova sede a Milano | News',
    'meta_description' => 'Apriamo una nuova sede a Milano.',
]);
$entryService->replaceValues($entryId, [
    ['field_id' => $bodyFieldId, 'language_id' => $itId, 'value_text' => '<p>Testo della notizia.</p>'],
]);

line("Contratto Theme↔CMS: permalink, SEO, stats, tutti coerenti su questa entry");
$permalink = $translationService->getPermalink($entryId, $itId);
echo "  permalink: {$permalink}\n";
if ($permalink !== '/it/news/nuova-sede-a-milano') {
    echo "  ERRORE: permalink_pattern non risolto come atteso (buildPermalink() prefissa sempre il codice lingua)!\n";
    exit(1);
}

$presenter = $cms->content('news')->published()->featured()->first();
echo "  title={$presenter->title} slug={$presenter->slug}\n";
echo '  fields[body]: ' . $presenter->fields['body'] . "\n";
if ($presenter->fields['body'] !== '<p>Testo della notizia.</p>') {
    echo "  ERRORE: field 'body' (richtext, traducibile) non risolto correttamente!\n";
    exit(1);
}

$cms->recordView($entryId);
$cms->recordView($entryId);
echo '  stats: view_count=' . $presenter->stats()->view_count . "\n";
if ($presenter->stats()->view_count !== 2) {
    echo "  ERRORE: supports_stats=true ma le viste non sono state tracciate!\n";
    exit(1);
}

line('done — tutti i controlli passati');
