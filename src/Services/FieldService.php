<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\FieldRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 *
 * type non è validato contro un registro dei Field Type: quel registro
 * (roadmap punto 8, "Field Type registry", punto di estensione assente in
 * giga-core) non esiste ancora — è un pezzo separato e successivo a
 * questo. Qui si valida solo che sia una stringa non vuota in formato
 * ragionevole, non che risolva a un Field Type realmente registrato.
 * DA STRINGERE quando il registro arriverà.
 *
 * config è JSON di configurazione del field (opzioni select, min/max
 * numero...) — metadato di definizione, non un valore di un'istanza: non è
 * soggetto alla restrizione su content_entry_values.value_json (vedi
 * ContentEntryService::replaceValues()).
 */
class FieldService
{
    private FieldRepository $fieldRepository;

    public function __construct()
    {
        $this->fieldRepository = new FieldRepository();
    }

    public function getById(int $id): array
    {
        $field = $this->fieldRepository->findById($id);
        if (!$field) {
            throw new \RuntimeException('Field non trovato.');
        }
        return $this->decodeConfig($field);
    }

    public function getAll(): array
    {
        return array_map($this->decodeConfig(...), $this->fieldRepository->findAll());
    }

    public function getFieldGroups(int $id): array
    {
        return $this->fieldRepository->getFieldGroups($id);
    }

    public function create(array $data): int
    {
        $key   = $this->validateKey($data['key'] ?? '', 0);
        $type  = $this->validateType($data['type'] ?? '');
        $label = trim($data['label'] ?? '');
        if ($label === '') {
            throw new \RuntimeException('La label è obbligatoria.');
        }

        return $this->fieldRepository->create([
            'key'          => $key,
            'label'        => $label,
            'type'         => $type,
            'translatable' => (int) (bool) ($data['translatable'] ?? false),
            'required'     => (int) (bool) ($data['required'] ?? false),
            'config'       => isset($data['config']) ? json_encode($data['config']) : null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->getById($id);
        $fields   = [];

        if (array_key_exists('key', $data) && $data['key'] !== $existing['key']) {
            $fields['key'] = $this->validateKey($data['key'], $id);
        }
        if (array_key_exists('label', $data)) {
            $label = trim($data['label']);
            if ($label === '') {
                throw new \RuntimeException('La label è obbligatoria.');
            }
            $fields['label'] = $label;
        }
        if (array_key_exists('type', $data)) {
            $fields['type'] = $this->validateType($data['type']);
        }
        if (array_key_exists('translatable', $data)) {
            $fields['translatable'] = (int) (bool) $data['translatable'];
        }
        if (array_key_exists('required', $data)) {
            $fields['required'] = (int) (bool) $data['required'];
        }
        if (array_key_exists('config', $data)) {
            $fields['config'] = $data['config'] !== null ? json_encode($data['config']) : null;
        }

        if ($fields !== []) {
            $this->fieldRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->fieldRepository->delete($id);
    }

    private function validateKey(string $key, int $excludeId): string
    {
        $key = trim($key);

        if ($key === '') {
            throw new \RuntimeException('La key è obbligatoria.');
        }
        if (!preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $key)) {
            throw new \RuntimeException('La key può contenere solo lettere minuscole, numeri e underscore.');
        }
        if ($this->fieldRepository->keyExists($key, $excludeId)) {
            throw new \RuntimeException("Esiste già un field con key '{$key}'.");
        }

        return $key;
    }

    private function validateType(string $type): string
    {
        $type = trim($type);
        if ($type === '') {
            throw new \RuntimeException('Il type è obbligatorio.');
        }
        return $type;
    }

    private function decodeConfig(array $field): array
    {
        $field['config'] = $field['config'] !== null ? json_decode($field['config'], true) : null;
        return $field;
    }
}
