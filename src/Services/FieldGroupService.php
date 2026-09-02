<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\FieldGroupRepository;
use Giga\Cms\Repositories\FieldRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 */
class FieldGroupService
{
    private FieldGroupRepository $groupRepository;
    private FieldRepository $fieldRepository;

    public function __construct()
    {
        $this->groupRepository = new FieldGroupRepository();
        $this->fieldRepository = new FieldRepository();
    }

    public function getById(int $id): array
    {
        $group = $this->groupRepository->findById($id);
        if (!$group) {
            throw new \RuntimeException('Field Group non trovato.');
        }
        return $group;
    }

    public function getAll(): array
    {
        return $this->groupRepository->findAll();
    }

    public function getFields(int $id): array
    {
        return $this->groupRepository->getFields($id);
    }

    public function create(array $data): int
    {
        $slug  = $this->validateSlug($data['slug'] ?? '', 0);
        $label = trim($data['label'] ?? '');
        if ($label === '') {
            throw new \RuntimeException('La label è obbligatoria.');
        }

        return $this->groupRepository->create([
            'slug'        => $slug,
            'label'       => $label,
            'description' => $data['description'] ?? null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->getById($id);
        $fields   = [];

        if (array_key_exists('slug', $data) && $data['slug'] !== $existing['slug']) {
            $fields['slug'] = $this->validateSlug($data['slug'], $id);
        }
        if (array_key_exists('label', $data)) {
            $label = trim($data['label']);
            if ($label === '') {
                throw new \RuntimeException('La label è obbligatoria.');
            }
            $fields['label'] = $label;
        }
        if (array_key_exists('description', $data)) {
            $fields['description'] = $data['description'];
        }

        if ($fields !== []) {
            $this->groupRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->groupRepository->delete($id);
    }

    /**
     * Sostituisce l'intera composizione del gruppo. $fieldIds nell'ordine
     * di visualizzazione desiderato.
     */
    public function syncFields(int $id, array $fieldIds): void
    {
        $this->getById($id);

        foreach ($fieldIds as $fieldId) {
            if (!$this->fieldRepository->findById((int) $fieldId)) {
                throw new \RuntimeException("Field id={$fieldId} non trovato.");
            }
        }

        $this->groupRepository->syncFields($id, array_map('intval', $fieldIds));
    }

    private function validateSlug(string $slug, int $excludeId): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new \RuntimeException('Lo slug è obbligatorio.');
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            throw new \RuntimeException('Lo slug può contenere solo lettere minuscole, numeri e trattini.');
        }
        if ($this->groupRepository->slugExists($slug, $excludeId)) {
            throw new \RuntimeException("Esiste già un Field Group con slug '{$slug}'.");
        }

        return $slug;
    }
}
