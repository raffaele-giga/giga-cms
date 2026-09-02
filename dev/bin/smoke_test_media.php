<?php

/**
 * Smoke test manuale di MediaService, delle gallery su ContentEntryService
 * e dei due nuovi Field Type (MediaFieldType, LinkFieldType kind 'file').
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\MediaService;
use Giga\Cms\Services\ContentTypeService;
use Giga\Cms\Services\ContentEntryService;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\FieldTypes\FieldTypeRegistry;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

$mediaService = new MediaService();
$typeService  = new ContentTypeService();
$entryService = new ContentEntryService();
$fieldRepo    = new FieldRepository();
$statusRepo   = new ContentStatusRepository();
$entryRepo    = new ContentEntryRepository();
$registry     = new FieldTypeRegistry();

line("Registry: 'media' ora registrato");
if (!$registry->has('media')) {
    echo "  ERRORE: 'media' dovrebbe essere registrato ora!\n";
    exit(1);
}
echo '  schema: ' . json_encode($registry->get('media')->schema()) . "\n";

line('MediaService::create — immagine pubblica');
$logoId = $mediaService->create([
    'path'      => '/uploads/2026/logo.png',
    'filename'  => 'logo.png',
    'mime_type' => 'image/png',
    'kind'      => 'image',
    'size'      => 15000,
    'width'     => 512,
    'height'    => 512,
    'alt'       => 'Logo aziendale',
]);
echo "  creato media id={$logoId}\n";
echo '  ' . json_encode($mediaService->getById($logoId)) . "\n";

line('MediaService::create — documento privato');
$docId = $mediaService->create([
    'path'      => '/private/2026/contratto.pdf',
    'filename'  => 'contratto.pdf',
    'mime_type' => 'application/pdf',
    'kind'      => 'document',
    'size'      => 250000,
    'is_public' => false,
]);
echo "  creato media id={$docId} (privato)\n";

line("MediaService::create con kind non valido -> deve fallire");
try {
    $mediaService->create(['path' => 'x', 'filename' => 'x', 'mime_type' => 'x', 'kind' => 'bogus', 'size' => 1]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('Setup: Content Type + field media (singolo) + field gallery + entry');
$typeId = $typeService->create([
    'slug' => 'media-test', 'label' => 'Media Test', 'label_singular' => 'Media Test',
]);
$featuredImageFieldId = $fieldRepo->create(['key' => 'featured_image', 'label' => 'Immagine in evidenza', 'type' => 'media']);
$galleryFieldId       = $fieldRepo->create(['key' => 'photo_gallery', 'label' => 'Galleria foto', 'type' => 'gallery']);
$draftStatus          = $statusRepo->findBySystemKey('draft');
$entryId              = $entryRepo->create(['content_type_id' => $typeId, 'status_id' => $draftStatus['id']]);

line("MediaFieldType via replaceValues: 'featured_image' -> value_media_id");
$row = array_merge(['field_id' => $featuredImageFieldId], $registry->get('media')->persist($logoId, []));
$entryService->replaceValues($entryId, [$row]);
echo '  values: ' . json_encode($entryService->getValues($entryId)) . "\n";

line('MediaFieldType con media inesistente -> deve fallire per FK');
$bogusRow = array_merge(['field_id' => $featuredImageFieldId], $registry->get('media')->persist(999999, []));
try {
    $entryService->replaceValues($entryId, [$bogusRow]);
    echo "  ERRORE: nessuna eccezione, la FK non ha protetto!\n";
    exit(1);
} catch (\PDOException $e) {
    echo '  bloccato correttamente dalla FK: ' . $e->getMessage() . "\n";
}

line("LinkFieldType kind 'file' -> ora sbloccato, usa value_media_id");
$link = $registry->get('link');
$link->validate(['kind' => 'file', 'value' => $docId], []);
$linkFieldId = $fieldRepo->create(['key' => 'attachment_link', 'label' => 'Allegato', 'type' => 'link']);
$fileRow = array_merge(['field_id' => $linkFieldId], $link->persist(['kind' => 'file', 'value' => $docId], []));
$entryService->replaceValues($entryId, [$fileRow]);
$values = $entryService->getValues($entryId);
foreach ($values as $v) {
    if ((int) $v['field_id'] === $linkFieldId) {
        echo '  render: ' . json_encode($link->render($v, [])) . "\n";
    }
}

line("ContentEntryService::replaceGallery su field 'photo_gallery' con 2 media");
$photo2Id = $mediaService->create([
    'path' => '/uploads/2026/photo2.jpg', 'filename' => 'photo2.jpg',
    'mime_type' => 'image/jpeg', 'kind' => 'image', 'size' => 20000,
]);
$entryService->replaceGallery($entryId, $galleryFieldId, [
    ['media_id' => $logoId, 'caption' => 'Prima foto'],
    ['media_id' => $photo2Id, 'caption' => 'Seconda foto'],
]);
foreach ($entryService->getGallery($entryId, $galleryFieldId) as $item) {
    printf("  #%d media_id=%d sort=%d caption=%s filename=%s\n", $item['id'], $item['media_id'], $item['sort_order'], $item['caption'], $item['filename']);
}

line("replaceGallery su un field che non è 'gallery' -> deve fallire");
try {
    $entryService->replaceGallery($entryId, $featuredImageFieldId, [['media_id' => $logoId]]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('FK RESTRICT: provo a cancellare un media ancora nella gallery -> deve fallire');
try {
    $mediaService->delete($logoId);
    echo "  ERRORE: la delete e' passata, non doveva (ancora nella gallery)!\n";
    exit(1);
} catch (\Throwable $e) {
    echo '  bloccata correttamente: ' . get_class($e) . "\n";
}

line('SET NULL: cancello il documento privato (usato solo come value_media_id, non in gallery) -> deve passare, il value diventa NULL');
$mediaService->delete($docId);
$linkRow = null;
foreach ($entryService->getValues($entryId) as $v) {
    if ((int) $v['field_id'] === $linkFieldId) {
        $linkRow = $v;
    }
}
echo '  value_media_id dopo la cancellazione del media referenziato: ' . var_export($linkRow['value_media_id'], true) . "\n";
if ($linkRow['value_media_id'] !== null) {
    echo "  ERRORE: doveva diventare NULL (SET NULL)!\n";
    exit(1);
}

line('Scioglimento pulito: svuoto la gallery, poi cancello il media senza errori');
$entryService->replaceGallery($entryId, $galleryFieldId, []);
$mediaService->delete($logoId);
$mediaService->delete($photo2Id);
echo "  cancellazioni pulite completate senza eccezioni\n";

line('done — tutti i controlli passati');
