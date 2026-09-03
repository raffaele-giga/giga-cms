<?php

namespace Giga\Cms\Services;

use Giga\Cms\Storage\MediaStorage;
use Giga\Cms\Storage\UploadMoverInterface;

/**
 * Verifica MIME reale (mai il MIME dichiarato dal client — Principi
 * trasversali, "Upload — MIME non attendibile") e distinzione fisica
 * pubblico/privato lato storage (Invariante #10). MediaService (invariato)
 * resta responsabile solo dei metadati già verificati, non dell'upload.
 *
 * Ordine di sicurezza non negoziabile, stesso ordine imposto da
 * MediaStorage: il file arriva sempre in storage privato prima di
 * qualunque verifica — non transita mai, nemmeno per un istante, nella
 * cartella pubblica prima che la verifica sia superata. Solo a verifica
 * positiva, se $isPublic, viene promosso. Qualunque eccezione dopo lo
 * spostamento iniziale cancella il file: nulla resta orfano su disco.
 */
class MediaUploadService
{
    /** Stesso ordine di grandezza del precedente reale (cms-neviobianchi DocumentService::MAX_SIZE). */
    private const MAX_SIZE = 20 * 1024 * 1024;

    /**
     * MIME reale ammesso -> kind (MediaService::KINDS). image/svg+xml è
     * deliberatamente assente da questa lista: non "non ancora
     * supportato", ma rifiutato sempre e esplicitamente — vedi
     * REJECTED_MIME_TYPES.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'image',
        'image/png'  => 'image',
        'image/gif'  => 'image',
        'image/webp' => 'image',
        'application/pdf' => 'pdf',
        'video/mp4'  => 'video',
        'video/webm' => 'video',
        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'text/plain' => 'document',
    ];

    /**
     * Rifiutati esplicitamente anche se il contenuto reale rilevato
     * corrisponde esattamente a questo MIME — mai per assenza dalla
     * whitelist. image/svg+xml: rischio XSS se caricato e servito senza
     * sanitizzazione (Principi trasversali) — nessun sanitizzatore è
     * integrato, quindi nessun SVG entra nell'Asset Library in V1.
     */
    private const REJECTED_MIME_TYPES = ['image/svg+xml'];

    private MediaStorage $storage;
    private MediaService $mediaService;

    /**
     * $uploadMover inoltrato invariato a MediaStorage — nessuna logica
     * di scelta qui, solo passaggio esplicito (vedi UploadMoverInterface).
     */
    public function __construct(?UploadMoverInterface $uploadMover = null)
    {
        $this->storage      = new MediaStorage($uploadMover);
        $this->mediaService = new MediaService();
    }

    /**
     * @param array $file una entry di $_FILES (tmp_name, name, size, error)
     * @param array $metadata is_public, alt, title, caption, credit, copyright
     */
    public function upload(array $file, array $metadata = []): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Caricamento del file fallito.');
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            throw new \RuntimeException('File troppo grande. Dimensione massima consentita: 20 MB.');
        }

        // Estensione solo come suggerimento per il nome file generato —
        // non decide mai il tipo: quello lo stabilisce solo il MIME reale
        // rilevato dopo lo spostamento in storage privato.
        $extensionHint = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'bin';
        $relativePath  = $this->storage->storeUploaded($file['tmp_name'], $extensionHint);

        try {
            return $this->verifyAndPersist($relativePath, $file['name'], $metadata);
        } catch (\Throwable $e) {
            // Verifica fallita (o qualunque errore successivo): il file
            // non deve sopravvivere su disco, né in privato né altrove.
            $this->storage->delete($relativePath, false);
            throw $e;
        }
    }

    private function verifyAndPersist(string $relativePath, string $originalFilename, array $metadata): int
    {
        $realMime = $this->storage->detectRealMimeType($relativePath);

        if (in_array($realMime, self::REJECTED_MIME_TYPES, true)) {
            throw new \RuntimeException(
                "Formato non consentito per motivi di sicurezza: '{$realMime}' (rischio XSS se servito senza sanitizzazione)."
            );
        }
        if (!array_key_exists($realMime, self::ALLOWED_MIME_TYPES)) {
            throw new \RuntimeException("Tipo di file non riconosciuto o non consentito: '{$realMime}'.");
        }

        $kind       = self::ALLOWED_MIME_TYPES[$realMime];
        $isPublic   = isset($metadata['is_public']) ? (bool) $metadata['is_public'] : true;
        $dimensions = $kind === 'image' ? $this->storage->getImageDimensions($relativePath) : null;
        $size       = $this->storage->fileSize($relativePath);

        // Promozione a pubblico SOLO qui, dopo la verifica positiva —
        // mai prima, mai per default.
        if ($isPublic) {
            $this->storage->moveToPublic($relativePath);
        }

        return $this->mediaService->create([
            'path'        => $relativePath,
            'filename'    => $originalFilename,
            'mime_type'   => $realMime,
            'kind'        => $kind,
            'size'        => $size,
            'width'       => $dimensions['width'] ?? null,
            'height'      => $dimensions['height'] ?? null,
            'is_public'   => $isPublic,
            'alt'         => $metadata['alt'] ?? null,
            'title'       => $metadata['title'] ?? null,
            'caption'     => $metadata['caption'] ?? null,
            'credit'      => $metadata['credit'] ?? null,
            'copyright'   => $metadata['copyright'] ?? null,
            'focal_point' => $metadata['focal_point'] ?? null,
        ]);
    }

    /** URL diretta per un asset pubblico, null per un privato (Invariante #10: mai una URL diretta per i privati). */
    public function publicUrl(array $media): ?string
    {
        return $media['is_public'] ? $this->storage->publicUrl($media['path']) : null;
    }

    /**
     * Path assoluto su disco, per lo streaming da un endpoint autorizzato
     * del progetto consumer (controllo permessi lì, mai qui — stesso
     * confine permission-agnostic di ContentEntryService). Funziona sia
     * per pubblici sia per privati: la scelta della root la fa is_public,
     * mai un parametro passato dal chiamante.
     */
    public function resolvePath(array $media): string
    {
        return $media['is_public']
            ? $this->storage->publicFullPath($media['path'])
            : $this->storage->privateFullPath($media['path']);
    }

    /** Cancella il file fisico (dalla root corretta) e la riga DB — MediaService::delete() resta solo-DB, invariato. */
    public function delete(int $mediaId): void
    {
        $media = $this->mediaService->getById($mediaId);
        $this->storage->delete($media['path'], (bool) $media['is_public']);
        $this->mediaService->delete($mediaId);
    }
}
