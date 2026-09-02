<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\MediaRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 *
 * CRUD sui metadati dell'Asset Library, presuppone il file già scritto su
 * storage e i suoi metadati (path, mime_type reale, size, width/height)
 * già noti al chiamante. Non gestisce l'upload effettivo (verifica MIME
 * reale, sanitizzazione SVG, storage, generazione varianti responsive,
 * endpoint di serving autorizzato per i privati) — quel layer non esiste
 * ancora ed è esplicitamente fuori scope qui: tocca l'Invariante #10 e il
 * principio "MIME non attendibile", merita un passaggio dedicato.
 */
class MediaService
{
    private const KINDS = ['image', 'video', 'pdf', 'document'];

    private MediaRepository $mediaRepository;

    public function __construct()
    {
        $this->mediaRepository = new MediaRepository();
    }

    public function getById(int $id): array
    {
        $media = $this->mediaRepository->findById($id);
        if (!$media) {
            throw new \RuntimeException('Media non trovato.');
        }
        return $media;
    }

    public function getPaginated(int $page, int $perPage, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = in_array($perPage, [10, 15, 25, 50]) ? $perPage : 15;

        $total      = $this->mediaRepository->countAll($filters);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = min($page, $totalPages);
        $items      = $this->mediaRepository->findPaginated($page, $perPage, $filters);

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    public function create(array $data): int
    {
        $kind = $this->validateKind($data['kind'] ?? '');

        if (empty($data['path'])) {
            throw new \RuntimeException('path è obbligatorio.');
        }
        if (empty($data['filename'])) {
            throw new \RuntimeException('filename è obbligatorio.');
        }
        if (empty($data['mime_type'])) {
            throw new \RuntimeException('mime_type è obbligatorio.');
        }
        if (!isset($data['size']) || (int) $data['size'] <= 0) {
            throw new \RuntimeException('size deve essere un numero di byte positivo.');
        }

        return $this->mediaRepository->create([
            'path'        => $data['path'],
            'filename'    => $data['filename'],
            'mime_type'   => $data['mime_type'],
            'kind'        => $kind,
            'size'        => (int) $data['size'],
            'width'       => isset($data['width']) ? (int) $data['width'] : null,
            'height'      => isset($data['height']) ? (int) $data['height'] : null,
            'is_public'   => isset($data['is_public']) ? (int) (bool) $data['is_public'] : 1,
            'alt'         => $data['alt'] ?? null,
            'title'       => $data['title'] ?? null,
            'caption'     => $data['caption'] ?? null,
            'credit'      => $data['credit'] ?? null,
            'copyright'   => $data['copyright'] ?? null,
            'focal_point' => $data['focal_point'] ?? null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $this->getById($id);

        $fields = array_intersect_key($data, array_flip([
            'is_public', 'alt', 'title', 'caption', 'credit', 'copyright', 'focal_point',
        ]));

        if (array_key_exists('is_public', $fields)) {
            $fields['is_public'] = (int) (bool) $fields['is_public'];
        }

        if ($fields !== []) {
            $this->mediaRepository->update($id, $fields);
        }
    }

    /**
     * Non cancella il file fisico: quello è responsabilità del layer di
     * storage, non ancora costruito. La FK RESTRICT su
     * content_entry_media.media_id blocca la delete se il media è ancora
     * in una gallery; value_media_id su content_entry_values invece
     * diventa NULL (SET NULL) senza bloccare.
     */
    public function delete(int $id): void
    {
        $this->getById($id);
        $this->mediaRepository->delete($id);
    }

    private function validateKind(string $kind): string
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \RuntimeException('kind non valido, atteso uno tra: ' . implode(', ', self::KINDS));
        }
        return $kind;
    }
}
