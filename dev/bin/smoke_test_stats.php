<?php

/**
 * Smoke test di content_entry_stats/supports_stats e della loro
 * esposizione nel Contratto Theme↔CMS: Cms::recordView() (unico punto
 * di scrittura raggiungibile dal tema) e $entry->stats() (lettura).
 *
 * Verifica in particolare: nessuna riga scritta per un Content Type con
 * supports_stats=false, incremento atomico su viste ripetute, default
 * zero per un'entry mai vista, CASCADE alla cancellazione dell'entry,
 * nessuna eccezione su un'entry inesistente.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Theme\Cms;
use Giga\Cms\Services\LanguageService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Services\ContentEntryTranslationService;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$languageService    = new LanguageService();
$typeService        = new ContentTypeService();
$entryService       = new ContentEntryService();
$translationService = new ContentEntryTranslationService();
$cms                = new Cms();
$db                 = \Giga\Core\Database::getInstance();

try {
    $itId = $languageService->getByCode('it')['id'];
} catch (\RuntimeException) {
    $itId = $languageService->create(['code' => 'it', 'name' => 'Italiano', 'locale' => 'it_IT']);
}

line("Setup: Content Type 'stats-news' con supports_stats=true");
$newsTypeId = $typeService->create([
    'slug' => 'stats-news', 'label' => 'News', 'label_singular' => 'News', 'supports_stats' => true,
]);
$newsEntry = $entryService->create(['content_type_id' => $newsTypeId]);
$entryService->publish($newsEntry);
$translationService->save($newsEntry, $itId, ['title' => 'Notizia', 'slug' => 'notizia']);

line("Setup: Content Type 'stats-team' con supports_stats=false (default)");
$teamTypeId = $typeService->create(['slug' => 'stats-team', 'label' => 'Team', 'label_singular' => 'Team']);
$teamEntry  = $entryService->create(['content_type_id' => $teamTypeId]);
$entryService->publish($teamEntry);
$translationService->save($teamEntry, $itId, ['title' => 'Persona', 'slug' => 'persona']);

line("entry mai vista -> stats() di default a zero, nessuna eccezione");
$newsPresenter = $cms->content('stats-news')->published()->first();
echo '  view_count=' . $newsPresenter->stats()->view_count . ' last_viewed_at=' . var_export($newsPresenter->stats()->last_viewed_at, true) . "\n";
if ($newsPresenter->stats()->view_count !== 0 || $newsPresenter->stats()->last_viewed_at !== null) {
    echo "  ERRORE: un'entry mai vista deve avere view_count=0 e last_viewed_at=null!\n";
    exit(1);
}

line('recordView() 3 volte sulla news -> view_count=3, last_viewed_at valorizzato');
$cms->recordView($newsEntry);
$cms->recordView($newsEntry);
$cms->recordView($newsEntry);
$newsPresenter = $cms->content('stats-news')->published()->first();
echo '  view_count=' . $newsPresenter->stats()->view_count . ' last_viewed_at=' . $newsPresenter->stats()->last_viewed_at . "\n";
if ($newsPresenter->stats()->view_count !== 3 || $newsPresenter->stats()->last_viewed_at === null) {
    echo "  ERRORE: l'incremento atomico su viste ripetute non ha funzionato!\n";
    exit(1);
}

line("recordView() su Content Type con supports_stats=false -> nessuna riga scritta");
$cms->recordView($teamEntry);
$cms->recordView($teamEntry);
$row = $db->fetchOne("SELECT * FROM content_entry_stats WHERE entry_id = ?", [$teamEntry]);
echo '  riga in content_entry_stats: ' . ($row ? 'esiste (ERRORE)' : 'assente (corretto)') . "\n";
if ($row !== false) {
    echo "  ERRORE: un Content Type con supports_stats=false non deve mai accumulare statistiche!\n";
    exit(1);
}

line("Presenter: stats() su un'entry di Content Type non abilitato -> default zero, nessun errore");
$teamPresenter = $cms->content('stats-team')->published()->first();
echo '  view_count=' . $teamPresenter->stats()->view_count . "\n";
if ($teamPresenter->stats()->view_count !== 0) {
    echo "  ERRORE: atteso default zero!\n";
    exit(1);
}

line('recordView() su entry inesistente -> nessuna eccezione, nessuna riga scritta');
$cms->recordView(999999);
echo "  ok, nessuna eccezione\n";

line('CASCADE: cancello fisicamente la news -> la sua riga in content_entry_stats sparisce con lei');
$db->execute("DELETE FROM content_entries WHERE id = ?", [$newsEntry]);
$row = $db->fetchOne("SELECT * FROM content_entry_stats WHERE entry_id = ?", [$newsEntry]);
echo '  riga rimasta dopo la cancellazione: ' . ($row ? 'SI (ERRORE)' : 'no (corretto)') . "\n";
if ($row !== false) {
    echo "  ERRORE: CASCADE non ha ripulito content_entry_stats!\n";
    exit(1);
}

line('done — tutti i controlli passati');
