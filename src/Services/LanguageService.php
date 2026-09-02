<?php

namespace Giga\Cms\Services;

use Giga\Core\Database;
use Giga\Cms\Repositories\LanguageRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 *
 * Invariante applicativa: al più una lingua con is_default=true. Non è un
 * vincolo DB (MySQL non esprime facilmente "al più una riga vera") — lo
 * garantisce create()/update() qui, in transazione.
 */
class LanguageService
{
    private Database $db;
    private LanguageRepository $languageRepository;

    public function __construct()
    {
        $this->db                 = Database::getInstance();
        $this->languageRepository = new LanguageRepository();
    }

    public function getById(int $id): array
    {
        $language = $this->languageRepository->findById($id);
        if (!$language) {
            throw new \RuntimeException('Lingua non trovata.');
        }
        return $language;
    }

    public function getByCode(string $code): array
    {
        $language = $this->languageRepository->findByCode($code);
        if (!$language) {
            throw new \RuntimeException("Lingua '{$code}' non trovata.");
        }
        return $language;
    }

    public function getAll(): array
    {
        return $this->languageRepository->findAll();
    }

    public function getActive(): array
    {
        return $this->languageRepository->findActive();
    }

    public function getDefault(): array
    {
        $language = $this->languageRepository->findDefault();
        if (!$language) {
            throw new \RuntimeException('Nessuna lingua di default configurata.');
        }
        return $language;
    }

    /** La prima lingua creata diventa default automaticamente, a prescindere da $data. */
    public function create(array $data): int
    {
        $code = $this->validateCode($data['code'] ?? '', 0);
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new \RuntimeException('Il nome è obbligatorio.');
        }
        $locale = trim($data['locale'] ?? '');
        if ($locale === '') {
            throw new \RuntimeException('Il locale è obbligatorio.');
        }

        $isFirst   = $this->languageRepository->findAll() === [];
        $isDefault = $isFirst || (bool) ($data['is_default'] ?? false);

        return $this->db->transaction(function () use ($code, $name, $locale, $isDefault, $data) {
            $id = $this->languageRepository->create([
                'code'       => $code,
                'name'       => $name,
                'locale'     => $locale,
                'is_default' => (int) $isDefault,
                'active'     => isset($data['active']) ? (int) (bool) $data['active'] : 1,
            ]);

            if ($isDefault) {
                $this->languageRepository->clearDefaultExcept($id);
            }

            return $id;
        });
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->getById($id);
        $fields   = [];

        if (array_key_exists('code', $data) && $data['code'] !== $existing['code']) {
            $fields['code'] = $this->validateCode($data['code'], $id);
        }
        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \RuntimeException('Il nome è obbligatorio.');
            }
            $fields['name'] = $name;
        }
        if (array_key_exists('locale', $data)) {
            $locale = trim($data['locale']);
            if ($locale === '') {
                throw new \RuntimeException('Il locale è obbligatorio.');
            }
            $fields['locale'] = $locale;
        }
        if (array_key_exists('active', $data)) {
            $fields['active'] = (int) (bool) $data['active'];
        }

        $makeDefault = array_key_exists('is_default', $data) && (bool) $data['is_default'] && !$existing['is_default'];
        if ($makeDefault) {
            $fields['is_default'] = 1;
        }

        $this->db->transaction(function () use ($id, $fields, $makeDefault) {
            if ($fields !== []) {
                $this->languageRepository->update($id, $fields);
            }
            if ($makeDefault) {
                $this->languageRepository->clearDefaultExcept($id);
            }
        });
    }

    /** Non permette di cancellare la lingua di default: va prima riassegnata esplicitamente. */
    public function delete(int $id): void
    {
        $language = $this->getById($id);
        if ($language['is_default']) {
            throw new \RuntimeException('Impossibile cancellare la lingua di default: assegnane prima un\'altra.');
        }
        $this->languageRepository->delete($id);
    }

    private function validateCode(string $code, int $excludeId): string
    {
        $code = trim(strtolower($code));

        if ($code === '') {
            throw new \RuntimeException('Il code è obbligatorio.');
        }
        if (!preg_match('/^[a-z]{2,10}$/', $code)) {
            throw new \RuntimeException('Il code può contenere solo lettere minuscole (es. it, en).');
        }
        if ($this->languageRepository->codeExists($code, $excludeId)) {
            throw new \RuntimeException("Esiste già una lingua con code '{$code}'.");
        }

        return $code;
    }
}
