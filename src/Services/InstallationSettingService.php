<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\InstallationSettingRepository;
use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\MediaRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 *
 * Chiave-valore generico (get/set) più accessor tipizzati per le chiavi
 * note del documento — evitano cast/validazione ripetuti nel codice
 * chiamante. Le chiavi che referenziano un'altra entità (homepage_entry_id,
 * i due *_media_id) hanno un setter dedicato che valida l'esistenza:
 * la tabella è chiave-valore, non può avere una FK reale a garantirlo.
 */
class InstallationSettingService
{
    /** Uniche chiavi accettate da set() — una stringa arbitraria non passa silenziosamente. */
    private const KNOWN_KEYS = [
        'site_name',
        'site_url',
        'site_logo_media_id',
        'theme',
        'homepage_entry_id',
        'page_builder_enabled',
        'content_type_authoring_enabled',
        'terminology',
        'seo_default_meta_title_suffix',
        'seo_default_og_media_id',
        'robots_txt_custom',
    ];

    private const DEFAULTS = [
        'theme'                           => 'default',
        'page_builder_enabled'            => '1',
        'content_type_authoring_enabled'  => '0',
        'terminology'                     => '{}',
    ];

    private InstallationSettingRepository $settingRepository;
    private ContentEntryRepository $entryRepository;
    private MediaRepository $mediaRepository;

    public function __construct()
    {
        $this->settingRepository = new InstallationSettingRepository();
        $this->entryRepository   = new ContentEntryRepository();
        $this->mediaRepository   = new MediaRepository();
    }

    /** Valore grezzo, o il default noto se la chiave non è mai stata impostata. */
    public function get(string $key): ?string
    {
        $value = $this->settingRepository->find($key);
        return $value !== null ? $value : (self::DEFAULTS[$key] ?? null);
    }

    public function set(string $key, ?string $value): void
    {
        if (!in_array($key, self::KNOWN_KEYS, true)) {
            throw new \RuntimeException("Chiave di impostazione sconosciuta: '{$key}'.");
        }
        $this->settingRepository->set($key, $value);
    }

    public function getBool(string $key): bool
    {
        return in_array($this->get($key), ['1', 'true', 'yes'], true);
    }

    public function getInt(string $key): ?int
    {
        $value = $this->get($key);
        return $value !== null && $value !== '' ? (int) $value : null;
    }

    public function getJson(string $key): array
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setJson(string $key, array $value): void
    {
        $this->set($key, json_encode($value));
    }

    /** Tutte le chiavi note, valorizzate o in fallback sul default — mai una chiave mancante nel risultato. */
    public function getAll(): array
    {
        $stored = $this->settingRepository->findAll();
        $result = [];
        foreach (self::KNOWN_KEYS as $key) {
            $result[$key] = $stored[$key] ?? (self::DEFAULTS[$key] ?? null);
        }
        return $result;
    }

    public function isPageBuilderEnabled(): bool
    {
        return $this->getBool('page_builder_enabled');
    }

    public function isContentTypeAuthoringEnabled(): bool
    {
        return $this->getBool('content_type_authoring_enabled');
    }

    public function getHomepageEntryId(): ?int
    {
        return $this->getInt('homepage_entry_id');
    }

    public function setHomepageEntry(int $entryId): void
    {
        if (!$this->entryRepository->findById($entryId)) {
            throw new \RuntimeException("Content entry id={$entryId} non trovata.");
        }
        $this->settingRepository->set('homepage_entry_id', (string) $entryId);
    }

    public function setSiteLogo(int $mediaId): void
    {
        if (!$this->mediaRepository->findById($mediaId)) {
            throw new \RuntimeException("Media id={$mediaId} non trovato.");
        }
        $this->settingRepository->set('site_logo_media_id', (string) $mediaId);
    }

    public function setSeoDefaultOgMedia(int $mediaId): void
    {
        if (!$this->mediaRepository->findById($mediaId)) {
            throw new \RuntimeException("Media id={$mediaId} non trovato.");
        }
        $this->settingRepository->set('seo_default_og_media_id', (string) $mediaId);
    }
}
