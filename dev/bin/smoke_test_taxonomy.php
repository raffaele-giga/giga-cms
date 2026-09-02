<?php

/**
 * Smoke test manuale di TaxonomyService/TaxonomyTermService, assegnazione
 * a Content Type e ai termini sulle entry.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\TaxonomyService;
use Giga\Cms\Services\TaxonomyTermService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Repositories\ContentStatusRepository;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$taxonomyService = new TaxonomyService();
$termService     = new TaxonomyTermService();
$typeService     = new ContentTypeService();
$entryService    = new ContentEntryService();
$statusRepo      = new ContentStatusRepository();

line("create() tassonomia flat 'tags'");
$tagsId = $taxonomyService->create(['slug' => 'tags', 'label' => 'Tags', 'label_singular' => 'Tag']);
echo "  creata id={$tagsId}\n";

line("create() tassonomia gerarchica 'categories'");
$categoriesId = $taxonomyService->create([
    'slug' => 'categories', 'label' => 'Categories', 'label_singular' => 'Category', 'is_hierarchical' => true,
]);
echo "  creata id={$categoriesId}\n";

line("create() termine su tassonomia flat con parent_id -> deve fallire (non gerarchica)");
$php = $termService->create(['taxonomy_id' => $tagsId, 'slug' => 'php', 'label' => 'PHP']);
try {
    $termService->create(['taxonomy_id' => $tagsId, 'slug' => 'js', 'label' => 'JS', 'parent_id' => $php]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("create() gerarchia su 'categories': Industry > Manufacturing, Industry > Creative");
$industryId     = $termService->create(['taxonomy_id' => $categoriesId, 'slug' => 'industry', 'label' => 'Industry']);
$manufacturingId = $termService->create(['taxonomy_id' => $categoriesId, 'slug' => 'manufacturing', 'label' => 'Manufacturing', 'parent_id' => $industryId]);
$creativeId     = $termService->create(['taxonomy_id' => $categoriesId, 'slug' => 'creative', 'label' => 'Creative', 'parent_id' => $industryId]);
foreach ($termService->getByTaxonomy($categoriesId) as $t) {
    printf("  #%d %-15s parent=%s\n", $t['id'], $t['slug'], $t['parent_id'] ?? 'NULL');
}

line("update() prova a rendere 'industry' figlio di 'manufacturing' -> deve fallire (ciclo)");
try {
    $termService->update($industryId, ['parent_id' => $manufacturingId]);
    echo "  ERRORE: nessuna eccezione, ciclo non rilevato!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("update() prova a rendere 'industry' parent di se stesso -> deve fallire");
try {
    $termService->update($industryId, ['parent_id' => $industryId]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("create() slug duplicato nella stessa tassonomia -> deve fallire, ma stesso slug in un'altra tassonomia e' ok");
try {
    $termService->create(['taxonomy_id' => $categoriesId, 'slug' => 'industry', 'label' => 'Dup']);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}
$sameSlugOtherTaxonomy = $termService->create(['taxonomy_id' => $tagsId, 'slug' => 'industry', 'label' => 'Industry (tag)']);
echo "  slug 'industry' riusato in un'altra tassonomia: OK, id={$sameSlugOtherTaxonomy}\n";

line("Setup: Content Type 'projects-tax-test' con tassonomia 'categories' assegnata (non 'tags')");
$typeId = $typeService->create(['slug' => 'projects-tax-test', 'label' => 'Projects', 'label_singular' => 'Project']);
$typeService->syncTaxonomies($typeId, [$categoriesId]);
foreach ($typeService->getTaxonomies($typeId) as $t) {
    printf("  content_type -> taxonomy #%d slug=%s\n", $t['id'], $t['slug']);
}

$draftStatus = $statusRepo->findBySystemKey('draft');
$entryId     = (new \Giga\Cms\Repositories\ContentEntryRepository())->create(['content_type_id' => $typeId, 'status_id' => $draftStatus['id']]);

line("syncTerms() su 'categories' (assegnata) con Manufacturing + Creative -> deve passare");
$entryService->syncTerms($entryId, $categoriesId, [$manufacturingId, $creativeId]);
foreach ($entryService->getTerms($entryId, $categoriesId) as $t) {
    printf("  #%d %s\n", $t['id'], $t['slug']);
}

line("syncTerms() su 'tags' (NON assegnata al Content Type) -> deve fallire");
try {
    $entryService->syncTerms($entryId, $tagsId, [$php]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("syncTerms() su 'categories' con un termine che appartiene a 'tags' -> deve fallire (mismatch tassonomia/termine)");
try {
    $entryService->syncTerms($entryId, $categoriesId, [$php]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("conferma: i termini validi assegnati prima non sono stati toccati dai tentativi falliti");
$terms = $entryService->getTerms($entryId, $categoriesId);
echo '  numero termini: ' . count($terms) . " (atteso 2)\n";
if (count($terms) !== 2) {
    echo "  ERRORE: i termini sono stati alterati nonostante le eccezioni!\n";
    exit(1);
}

line("FK RESTRICT: provo a cancellare 'manufacturing' -> deve fallire (ancora assegnato all'entry)");
try {
    $termService->delete($manufacturingId);
    echo "  ERRORE: la delete e' passata, non doveva!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}

line("FK RESTRICT: provo a cancellare 'industry' -> deve fallire (ha ancora figli)");
try {
    $termService->delete($industryId);
    echo "  ERRORE: la delete e' passata, non doveva!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}

line("FK RESTRICT: provo a cancellare la tassonomia 'categories' -> deve fallire (ha ancora termini)");
try {
    $taxonomyService->delete($categoriesId);
    echo "  ERRORE: la delete e' passata, non doveva!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}

line('Scioglimento pulito: svuoto termini + assegnazione al Content Type, poi cancello foglie -> radice -> tassonomia');
$entryService->syncTerms($entryId, $categoriesId, []);
$typeService->syncTaxonomies($typeId, []);
$termService->delete($manufacturingId);
$termService->delete($creativeId);
$termService->delete($industryId);
$taxonomyService->delete($categoriesId);
echo "  cancellazioni pulite completate senza eccezioni\n";

line('done — tutti i controlli passati');
