<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\TaxonomyRepository;
use Giga\Cms\Repositories\TaxonomyTermRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 */
class TaxonomyTermService
{
    private TaxonomyRepository $taxonomyRepository;
    private TaxonomyTermRepository $termRepository;

    public function __construct()
    {
        $this->taxonomyRepository = new TaxonomyRepository();
        $this->termRepository     = new TaxonomyTermRepository();
    }

    public function getById(int $id): array
    {
        $term = $this->termRepository->findById($id);
        if (!$term) {
            throw new \RuntimeException('Termine non trovato.');
        }
        return $term;
    }

    public function getByTaxonomy(int $taxonomyId): array
    {
        return $this->termRepository->findByTaxonomy($taxonomyId);
    }

    public function create(array $data): int
    {
        $taxonomyId = (int) ($data['taxonomy_id'] ?? 0);
        $taxonomy   = $this->taxonomyRepository->findById($taxonomyId);
        if (!$taxonomy) {
            throw new \RuntimeException('Tassonomia non trovata.');
        }

        $slug  = $this->validateSlug($taxonomyId, $data['slug'] ?? '', 0);
        $label = trim($data['label'] ?? '');
        if ($label === '') {
            throw new \RuntimeException('La label è obbligatoria.');
        }

        $parentId = null;
        if (!empty($data['parent_id'])) {
            $parentId = $this->validateParent($taxonomy, (int) $data['parent_id'], null);
        }

        return $this->termRepository->create([
            'taxonomy_id' => $taxonomyId,
            'parent_id'   => $parentId,
            'slug'        => $slug,
            'label'       => $label,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->getById($id);
        $fields   = [];

        if (array_key_exists('slug', $data) && $data['slug'] !== $existing['slug']) {
            $fields['slug'] = $this->validateSlug($existing['taxonomy_id'], $data['slug'], $id);
        }
        if (array_key_exists('label', $data)) {
            $label = trim($data['label']);
            if ($label === '') {
                throw new \RuntimeException('La label è obbligatoria.');
            }
            $fields['label'] = $label;
        }
        if (array_key_exists('sort_order', $data)) {
            $fields['sort_order'] = (int) $data['sort_order'];
        }
        if (array_key_exists('parent_id', $data)) {
            if ($data['parent_id'] !== null) {
                $taxonomy = $this->taxonomyRepository->findById((int) $existing['taxonomy_id']);
                if (!$taxonomy) {
                    throw new \RuntimeException('Tassonomia non trovata.');
                }
                $fields['parent_id'] = $this->validateParent($taxonomy, (int) $data['parent_id'], $id);
            } else {
                $fields['parent_id'] = null;
            }
        }

        if ($fields !== []) {
            $this->termRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->termRepository->delete($id);
    }

    private function validateSlug(int $taxonomyId, string $slug, int $excludeId): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new \RuntimeException('Lo slug è obbligatorio.');
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            throw new \RuntimeException('Lo slug può contenere solo lettere minuscole, numeri e trattini.');
        }
        if ($this->termRepository->slugExists($taxonomyId, $slug, $excludeId)) {
            throw new \RuntimeException("Esiste già un termine con slug '{$slug}' in questa tassonomia.");
        }

        return $slug;
    }

    /**
     * $currentId è null in creazione (nessun ciclo possibile: il termine
     * non esiste ancora). In update, verifica che il nuovo parent non sia
     * il termine stesso né un suo discendente (risalendo la catena
     * parent_id del candidato: se incontra $currentId, sarebbe un ciclo).
     */
    private function validateParent(array $taxonomy, int $parentId, ?int $currentId): int
    {
        if (!$taxonomy['is_hierarchical']) {
            throw new \RuntimeException("La tassonomia '{$taxonomy['slug']}' non è gerarchica: parent_id non ammesso.");
        }
        if ($currentId !== null && $parentId === $currentId) {
            throw new \RuntimeException('Un termine non può essere parent di se stesso.');
        }

        $parent = $this->termRepository->findById($parentId);
        if (!$parent) {
            throw new \RuntimeException('Il termine parent indicato non esiste.');
        }
        if ((int) $parent['taxonomy_id'] !== (int) $taxonomy['id']) {
            throw new \RuntimeException('Il termine parent deve appartenere alla stessa tassonomia.');
        }

        if ($currentId !== null) {
            $ancestor = $parent;
            while ($ancestor !== null) {
                if ((int) $ancestor['id'] === $currentId) {
                    throw new \RuntimeException('Assegnazione rifiutata: creerebbe un ciclo nella gerarchia.');
                }
                $ancestor = $ancestor['parent_id'] !== null
                    ? $this->termRepository->findById((int) $ancestor['parent_id']) ?: null
                    : null;
            }
        }

        return $parentId;
    }
}
