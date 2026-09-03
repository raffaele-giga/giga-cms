<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\BlockTypeRepository;
use Giga\Cms\Repositories\FieldGroupRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 */
class BlockTypeService
{
    private BlockTypeRepository $blockTypeRepository;
    private FieldGroupRepository $fieldGroupRepository;

    public function __construct()
    {
        $this->blockTypeRepository  = new BlockTypeRepository();
        $this->fieldGroupRepository = new FieldGroupRepository();
    }

    public function getById(int $id): array
    {
        $blockType = $this->blockTypeRepository->findById($id);
        if (!$blockType) {
            throw new \RuntimeException('Block Type non trovato.');
        }
        return $blockType;
    }

    public function getBySlug(string $slug): array
    {
        $blockType = $this->blockTypeRepository->findBySlug($slug);
        if (!$blockType) {
            throw new \RuntimeException("Block Type '{$slug}' non trovato.");
        }
        return $blockType;
    }

    public function getAll(): array
    {
        return $this->blockTypeRepository->findAll();
    }

    public function create(array $data): int
    {
        $slug  = $this->validateSlug($data['slug'] ?? '', 0);
        $label = trim($data['label'] ?? '');
        if ($label === '') {
            throw new \RuntimeException('La label è obbligatoria.');
        }

        return $this->blockTypeRepository->create(['slug' => $slug, 'label' => $label]);
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

        if ($fields !== []) {
            $this->blockTypeRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->blockTypeRepository->delete($id);
    }

    public function getFieldGroups(int $id): array
    {
        return $this->blockTypeRepository->getFieldGroups($id);
    }

    /** Sostituisce l'intera assegnazione di Field Group. $fieldGroupIds nell'ordine di visualizzazione desiderato. */
    public function syncFieldGroups(int $id, array $fieldGroupIds): void
    {
        $this->getById($id);

        foreach ($fieldGroupIds as $fieldGroupId) {
            if (!$this->fieldGroupRepository->findById((int) $fieldGroupId)) {
                throw new \RuntimeException("Field Group id={$fieldGroupId} non trovato.");
            }
        }

        $this->blockTypeRepository->syncFieldGroups($id, array_map('intval', $fieldGroupIds));
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
        if ($this->blockTypeRepository->slugExists($slug, $excludeId)) {
            throw new \RuntimeException("Esiste già un Block Type con slug '{$slug}'.");
        }

        return $slug;
    }
}
