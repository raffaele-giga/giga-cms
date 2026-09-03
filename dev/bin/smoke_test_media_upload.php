<?php

/**
 * Smoke test di MediaStorage/MediaUploadService: verifica MIME reale
 * (mai il MIME/estensione dichiarati), separazione fisica pubblico/
 * privato, rifiuto di un SVG travestito da .jpg (content-sniffing batte
 * l'estensione), nessun file orfano su un upload rifiutato, delete()
 * che rimuove anche il file fisico.
 */

require dirname(__DIR__) . '/bootstrap.php';

use Giga\Cms\Services\MediaUploadService;
use Giga\Cms\Services\MediaService;
use Giga\Cms\Storage\LocalFileUploadMover;

function line(string $label): void
{
    echo "\n--- {$label} ---\n";
}

function makeUploadedFile(string $content, string $name): array
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'giga_upload_');
    file_put_contents($tmpPath, $content);

    return [
        'name'     => $name,
        'type'     => 'application/octet-stream', // deliberatamente sbagliato/inattendibile: non deve mai essere usato
        'tmp_name' => $tmpPath,
        'error'    => UPLOAD_ERR_OK,
        'size'     => strlen($content),
    ];
}

function makePng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);
    $content = ob_get_clean();
    imagedestroy($image);
    return $content;
}

// Harness CLI: dichiara esplicitamente il mover locale (rename()) invece
// del default di produzione (move_uploaded_file(), che qui fallirebbe
// sempre perché $tmp_name non è un vero upload HTTP gestito da PHP).
$uploadService = new MediaUploadService(new LocalFileUploadMover());
$mediaService  = new MediaService();

$publicRoot  = dirname(__DIR__) . '/public/media';
$privateRoot = dirname(__DIR__) . '/storage/media';

line('upload() pubblico: PNG reale, dichiarato type=application/octet-stream (deve essere ignorato)');
$publicFile = makeUploadedFile(makePng(4, 3), 'foto.png');
$publicId   = $uploadService->upload($publicFile, ['is_public' => true, 'alt' => 'Foto di prova']);
$publicMedia = $mediaService->getById($publicId);
echo '  ' . json_encode($publicMedia) . "\n";
if ($publicMedia['mime_type'] !== 'image/png' || $publicMedia['kind'] !== 'image') {
    echo "  ERRORE: il MIME dichiarato dal client ha vinto sul MIME reale rilevato!\n";
    exit(1);
}
if ((int) $publicMedia['width'] !== 4 || (int) $publicMedia['height'] !== 3) {
    echo "  ERRORE: dimensioni immagine non estratte correttamente!\n";
    exit(1);
}

line('Verifica fisica: il file pubblico esiste in public_path, NON in private_path');
$publicFullPath  = $publicRoot . '/' . $publicMedia['path'];
$privateFullPath = $privateRoot . '/' . $publicMedia['path'];
echo "  public_path: " . (file_exists($publicFullPath) ? 'esiste' : 'MANCANTE') . "\n";
echo "  private_path: " . (file_exists($privateFullPath) ? 'esiste (ERRORE)' : 'assente (corretto)') . "\n";
if (!file_exists($publicFullPath) || file_exists($privateFullPath)) {
    echo "  ERRORE: la separazione fisica pubblico/privato non ha funzionato!\n";
    exit(1);
}

line('publicUrl()/resolvePath() per un asset pubblico');
echo '  publicUrl: ' . $uploadService->publicUrl($publicMedia) . "\n";
echo '  resolvePath: ' . $uploadService->resolvePath($publicMedia) . "\n";
if ($uploadService->publicUrl($publicMedia) === null) {
    echo "  ERRORE: un asset pubblico deve avere una publicUrl!\n";
    exit(1);
}

line('upload() PRIVATO: stesso PNG, is_public=false');
$privateFile  = makeUploadedFile(makePng(2, 2), 'privato.png');
$privateId    = $uploadService->upload($privateFile, ['is_public' => false]);
$privateMedia = $mediaService->getById($privateId);
$privateFullPath2 = $privateRoot . '/' . $privateMedia['path'];
$publicFullPath2  = $publicRoot . '/' . $privateMedia['path'];
echo "  private_path: " . (file_exists($privateFullPath2) ? 'esiste (corretto)' : 'MANCANTE') . "\n";
echo "  public_path: " . (file_exists($publicFullPath2) ? 'esiste (ERRORE)' : 'assente (corretto)') . "\n";
if (!file_exists($privateFullPath2) || file_exists($publicFullPath2)) {
    echo "  ERRORE: un asset privato non deve mai transitare in public_path!\n";
    exit(1);
}
echo '  publicUrl() per un privato: ' . var_export($uploadService->publicUrl($privateMedia), true) . " (atteso NULL, Invariante #10)\n";
if ($uploadService->publicUrl($privateMedia) !== null) {
    echo "  ERRORE: un asset privato non deve mai avere una publicUrl (Invariante #10)!\n";
    exit(1);
}

line("upload() di un SVG travestito da .jpg -> content-sniffing deve batter l'estensione dichiarata, rifiuto esplicito");
$svgContent = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
$svgFile    = makeUploadedFile($svgContent, 'immagine-innocua.jpg');
try {
    $uploadService->upload($svgFile, ['is_public' => true]);
    echo "  ERRORE: nessuna eccezione, l'SVG travestito e' passato!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line('Conferma: nessun file orfano rimasto su disco dopo il rifiuto dello SVG');
$publicFilesAfterReject  = glob($publicRoot . '/*/*/*');
$privateFilesAfterReject = glob($privateRoot . '/*/*/*');
echo '  file in public_path: ' . count($publicFilesAfterReject) . " (atteso 1, solo il PNG pubblico di prima)\n";
echo '  file in private_path: ' . count($privateFilesAfterReject) . " (atteso 1, solo il PNG privato di prima)\n";
if (count($publicFilesAfterReject) !== 1 || count($privateFilesAfterReject) !== 1) {
    echo "  ERRORE: l'SVG rifiutato ha lasciato un file orfano su disco!\n";
    exit(1);
}

line('upload() di un tipo genuinamente non riconosciuto -> rifiuto, nessun orfano');
$unknownFile = makeUploadedFile(random_bytes(64), 'misterioso.dat');
try {
    $uploadService->upload($unknownFile, ['is_public' => true]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}
$publicFilesAfterUnknown = glob($publicRoot . '/*/*/*');
if (count($publicFilesAfterUnknown) !== 1) {
    echo "  ERRORE: il tipo sconosciuto rifiutato ha lasciato un file orfano!\n";
    exit(1);
}

line('upload() oltre il limite di dimensione dichiarato -> rifiuto prima ancora di toccare lo storage');
$oversized = makeUploadedFile('contenuto minuscolo', 'grande.png');
$oversized['size'] = 25 * 1024 * 1024;
try {
    $uploadService->upload($oversized, ['is_public' => true]);
    echo "  ERRORE: nessuna eccezione!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  bloccato correttamente: ' . $e->getMessage() . "\n";
}

line("delete(): rimuove sia la riga DB sia il file fisico");
$uploadService->delete($publicId);
echo '  file pubblico ancora su disco dopo delete(): ' . (file_exists($publicFullPath) ? 'SI (ERRORE)' : 'no (corretto)') . "\n";
if (file_exists($publicFullPath)) {
    echo "  ERRORE: delete() non ha rimosso il file fisico!\n";
    exit(1);
}
try {
    $mediaService->getById($publicId);
    echo "  ERRORE: la riga DB doveva essere stata cancellata!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo '  riga DB cancellata correttamente: ' . $e->getMessage() . "\n";
}

line('done — tutti i controlli passati');
