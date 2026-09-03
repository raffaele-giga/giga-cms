<?php

namespace Giga\Cms\Storage;

/**
 * Unica classe che tocca il filesystem per l'Asset Library. Legge
 * config/media.php dal progetto consumer (stesso pattern di
 * Giga\Core\Database con config/database.php: require ROOT_PATH . '/config/...').
 *
 * public_path/private_path sono radici FISICAMENTE separate, non una
 * cartella con sottocartelle pubblica/privata — è questo, non il flag
 * is_public in DB, che fa rispettare l'Invariante #10 (un asset privato
 * non è mai servito da URL pubblico diretto).
 *
 * Ordine di sicurezza non negoziabile: ogni file caricato arriva SEMPRE
 * in private_path per primo (storeUploaded), mai in public_path prima di
 * una verifica. Solo dopo una verifica positiva del chiamante
 * (MediaUploadService) un file destinato a essere pubblico viene
 * promosso esplicitamente con moveToPublic() — un passo separato e
 * successivo, mai implicito in storeUploaded().
 */
class MediaStorage
{
    private string $publicPath;
    private string $privatePath;
    private string $publicUrl;
    private UploadMoverInterface $uploadMover;

    /**
     * $uploadMover di default a HttpUploadMover (move_uploaded_file(),
     * comportamento di produzione) — chi vuole un mover diverso
     * (LocalFileUploadMover, per CLI/import) lo dichiara passandolo
     * esplicitamente, mai dedotto dall'ambiente dentro questa classe.
     */
    public function __construct(?UploadMoverInterface $uploadMover = null)
    {
        $config = require ROOT_PATH . '/config/media.php';

        $this->publicPath  = rtrim($config['public_path'], '/');
        $this->privatePath = rtrim($config['private_path'], '/');
        $this->publicUrl   = rtrim($config['public_url'], '/');
        $this->uploadMover = $uploadMover ?? new HttpUploadMover();

        $this->ensureDirectory($this->privatePath);
        $this->ensureDirectory($this->publicPath);
    }

    /**
     * Sposta un file appena caricato (tmp_name di $_FILES) in
     * private_path — sempre, indipendentemente dalla destinazione finale
     * che avrà. Nessuna verifica qui: è compito del chiamante farla
     * PRIMA di decidere se promuovere il file a pubblico.
     */
    public function storeUploaded(string $tmpPath, string $extensionHint): string
    {
        $relativePath = $this->generateRelativePath($extensionHint);
        $destination  = $this->privatePath . '/' . $relativePath;
        $this->ensureDirectory(dirname($destination));

        if (!$this->uploadMover->move($tmpPath, $destination)) {
            throw new \RuntimeException('Errore durante il salvataggio del file caricato.');
        }

        return $relativePath;
    }

    /**
     * Promuove a pubblico un file già verificato e presente in
     * private_path: lo copia in public_path e rimuove l'originale
     * privato, lasciando un'unica copia finale coerente con is_public=true.
     */
    public function moveToPublic(string $relativePath): void
    {
        $source      = $this->privatePath . '/' . $relativePath;
        $destination = $this->publicPath . '/' . $relativePath;
        $this->ensureDirectory(dirname($destination));

        if (!copy($source, $destination)) {
            throw new \RuntimeException('Errore durante la pubblicazione del file.');
        }
        unlink($source);
    }

    public function privateFullPath(string $relativePath): string
    {
        return $this->privatePath . '/' . $relativePath;
    }

    public function publicFullPath(string $relativePath): string
    {
        return $this->publicPath . '/' . $relativePath;
    }

    public function publicUrl(string $relativePath): string
    {
        return $this->publicUrl . '/' . $relativePath;
    }

    public function delete(string $relativePath, bool $isPublic): void
    {
        $path = $isPublic ? $this->publicFullPath($relativePath) : $this->privateFullPath($relativePath);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Tipo reale rilevato dal contenuto del file (mai il MIME dichiarato
     * dal client) — verificato mentre il file è ancora in private_path,
     * prima di qualunque eventuale promozione a pubblico.
     */
    public function detectRealMimeType(string $relativePath): string
    {
        $mime = mime_content_type($this->privateFullPath($relativePath));
        if ($mime === false) {
            throw new \RuntimeException('Impossibile determinare il tipo reale del file.');
        }
        return $mime;
    }

    /** @return array{width:int,height:int}|null null se il file non è un'immagine leggibile */
    public function getImageDimensions(string $relativePath): ?array
    {
        $info = @getimagesize($this->privateFullPath($relativePath));
        return $info !== false ? ['width' => $info[0], 'height' => $info[1]] : null;
    }

    public function fileSize(string $relativePath): int
    {
        return filesize($this->privateFullPath($relativePath));
    }

    private function generateRelativePath(string $extension): string
    {
        $subdir   = date('Y/m');
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        return $subdir . '/' . $filename;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
