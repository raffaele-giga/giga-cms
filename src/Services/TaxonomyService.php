<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\TaxonomyRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 */
class TaxonomyService
{
    private TaxonomyRepository $taxonomyRepository;

    public function __construct()
    {
        $this->taxonomyRepository = new TaxonomyRepository();
    }

    public function getById(int $id): array
    {
        $taxonomy = $this->taxonomyRepository->findById($id);
        if (!$taxonomy) {
            throw new \RuntimeException('Tassonomia non trovata.');
        }
        return $taxonomy;
    }

    public function getBySlug(string $slug): array
    {
        $taxonomy = $this->taxonomyRepository->findBySlug($slug);
        if (!$taxonomy) {
            throw new \RuntimeException("Tassonomia '{$slug}' non trovata.");
        }
        return $taxonomy;
    }

    public function getAll(): array
    {
        return $this->taxonomyRepository->findAll();
    }

    public function create(array $data): int
    {
        $slug  = $this->validateSlug($data['slug'] ?? '', 0);
        $label = trim($data['label'] ?? '');
        if ($label === '') {
            throw new \RuntimeException('La label è obbligatoria.');
        }
        $labelSingular = trim($data['label_singular'] ?? '');
        if ($labelSingular === '') {
            throw new \RuntimeException('La label singolare è obbligatoria.');
        }

        return $this->taxonomyRepository->create([
            'slug'            => $slug,
            'label'           => $label,
            'label_singular'  => $labelSingular,
            'is_hierarchical' => (int) (bool) ($data['is_hierarchical'] ?? false),
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
        if (array_key_exists('label_singular', $data)) {
            $labelSingular = trim($data['label_singular']);
            if ($labelSingular === '') {
                throw new \RuntimeException('La label singolare è obbligatoria.');
            }
            $fields['label_singular'] = $labelSingular;
        }
        if (array_key_exists('is_hierarchical', $data)) {
            $fields['is_hierarchical'] = (int) (bool) $data['is_hierarchical'];
        }

        if ($fields !== []) {
            $this->taxonomyRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->taxonomyRepository->delete($id);
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
        if ($this->taxonomyRepository->slugExists($slug, $excludeId)) {
            throw new \RuntimeException("Esiste già una tassonomia con slug '{$slug}'.");
        }

        return $slug;
    }
}
