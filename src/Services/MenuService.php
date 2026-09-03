<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\MenuRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 */
class MenuService
{
    private MenuRepository $menuRepository;

    public function __construct()
    {
        $this->menuRepository = new MenuRepository();
    }

    public function getById(int $id): array
    {
        $menu = $this->menuRepository->findById($id);
        if (!$menu) {
            throw new \RuntimeException('Menu non trovato.');
        }
        return $menu;
    }

    public function getBySlug(string $slug): array
    {
        $menu = $this->menuRepository->findBySlug($slug);
        if (!$menu) {
            throw new \RuntimeException("Menu '{$slug}' non trovato.");
        }
        return $menu;
    }

    public function getAll(): array
    {
        return $this->menuRepository->findAll();
    }

    public function create(array $data): int
    {
        $slug  = $this->validateSlug($data['slug'] ?? '', 0);
        $label = trim($data['label'] ?? '');
        if ($label === '') {
            throw new \RuntimeException('La label è obbligatoria.');
        }

        return $this->menuRepository->create(['slug' => $slug, 'label' => $label]);
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
            $this->menuRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->menuRepository->delete($id);
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
        if ($this->menuRepository->slugExists($slug, $excludeId)) {
            throw new \RuntimeException("Esiste già un menu con slug '{$slug}'.");
        }

        return $slug;
    }
}
