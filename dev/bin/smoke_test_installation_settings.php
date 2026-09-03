<?php

/**
 * Smoke test di InstallationSettingService (get/set generico, accessor
 * tipizzati, validazione delle chiavi che referenziano un'altra entità)
 * e RobotsTxtService (generato di default, override custom totale).
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\InstallationSettingService;
use Giga\Cms\Services\RobotsTxtService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\MediaService;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\ContentEntryRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$settingService = new InstallationSettingService();
$robotsService  = new RobotsTxtService();
$typeService    = new ContentTypeService();
$mediaService   = new MediaService();

line('Default noti presenti fin da subito (seed di migration)');
echo '  theme=' . $settingService->get('theme') . "\n";
echo '  page_builder_enabled=' . var_export($settingService->isPageBuilderEnabled(), true) . "\n";
echo '  content_type_authoring_enabled=' . var_export($settingService->isContentTypeAuthoringEnabled(), true) . "\n";
echo '  terminology=' . json_encode($settingService->getJson('terminology')) . "\n";
if ($settingService->get('theme') !== 'default' || !$settingService->isPageBuilderEnabled() || $settingService->isContentTypeAuthoringEnabled()) {
    echo "  ERRORE: default seedati dalla migration non coincidono con l'atteso!\n";
    exit(1);
}

line("set() su una chiave sconosciuta -> deve fallire");
try {
    $settingService->set('chiave_a_caso', 'x');
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('set()/get() su site_name, site_url');
$settingService->set('site_name', 'Acme Corp');
$settingService->set('site_url', 'https://acme.example.com/');
echo '  site_name=' . $settingService->get('site_name') . "\n";
echo '  site_url=' . $settingService->get('site_url') . "\n";

line('setJson() su terminology, getJson() lo ricostruisce');
$settingService->setJson('terminology', ['content_type' => 'Sezione', 'content_entry' => 'Scheda']);
$terminology = $settingService->getJson('terminology');
echo '  ' . json_encode($terminology) . "\n";
if ($terminology['content_type'] !== 'Sezione') {
    echo "  ERRORE: terminology non round-trippa correttamente attraverso JSON!\n";
    exit(1);
}

line("setHomepageEntry() con un'entry inesistente -> deve fallire");
try {
    $settingService->setHomepageEntry(999999);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("setHomepageEntry() con un'entry reale -> deve passare");
$typeId  = $typeService->create(['slug' => 'settings-test', 'label' => 'ST', 'label_singular' => 'ST']);
$draft   = (new ContentStatusRepository())->findBySystemKey('draft');
$entryId = (new ContentEntryRepository())->create(['content_type_id' => $typeId, 'status_id' => $draft['id']]);
$settingService->setHomepageEntry($entryId);
echo '  getHomepageEntryId()=' . $settingService->getHomepageEntryId() . "\n";
if ($settingService->getHomepageEntryId() !== $entryId) {
    echo "  ERRORE: homepage_entry_id non salvato/letto correttamente!\n";
    exit(1);
}

line("setSiteLogo() con un media inesistente -> deve fallire");
try {
    $settingService->setSiteLogo(999999);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('setSiteLogo() con un media reale -> deve passare');
$mediaId = $mediaService->create(['path' => '/uploads/logo.png', 'filename' => 'logo.png', 'mime_type' => 'image/png', 'kind' => 'image', 'size' => 2000]);
$settingService->setSiteLogo($mediaId);
echo '  site_logo_media_id=' . $settingService->getInt('site_logo_media_id') . "\n";

line('getAll() include tutte le chiavi note, valorizzate o in fallback sul default');
$all = $settingService->getAll();
echo '  ' . json_encode($all, JSON_PRETTY_PRINT) . "\n";
if (!array_key_exists('robots_txt_custom', $all) || $all['robots_txt_custom'] !== null) {
    echo "  ERRORE: una chiave nota mai impostata deve comparire con valore NULL, non essere assente!\n";
    exit(1);
}

line('RobotsTxtService::generate() -- default, con Sitemap (site_url impostato sopra)');
echo $robotsService->generate();
if (!str_contains($robotsService->generate(), 'Sitemap: https://acme.example.com/sitemap.xml')) {
    echo "  ERRORE: il default generato non referenzia la sitemap!\n";
    exit(1);
}

line('RobotsTxtService::generate() -- override custom totale');
$settingService->set('robots_txt_custom', "User-agent: *\nDisallow: /admin\n");
echo $robotsService->generate();
if ($robotsService->generate() !== "User-agent: *\nDisallow: /admin\n") {
    echo "  ERRORE: l'override custom non ha sostituito integralmente il generato!\n";
    exit(1);
}

line('done — tutti i controlli passati');
