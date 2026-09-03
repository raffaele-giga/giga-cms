<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\MenuRepository;
use Giga\Cms\Repositories\MenuItemRepository;

/**
 * Permission-agnostic per design, vedi README.md e ContentEntryService.
 *
 * target_variant replica lo schema di LinkFieldType (stesso problema:
 * "pagina interna, Content Entry, URL esterno, anchor" — un target che
 * cambia colonna in base al kind). "Pagina interna" e "Content Entry"
 * sono unificati nel kind 'entry': una Page è semplicemente un'entry del
 * Content Type di sistema "Page", nessuna distinzione da modellare.
 */
class MenuItemService
{
    private const KNOWN_VARIANTS = ['entry', 'url', 'anchor'];

    private MenuRepository $menuRepository;
    private MenuItemRepository $itemRepository;

    public function __construct()
    {
        $this->menuRepository = new MenuRepository();
        $this->itemRepository = new MenuItemRepository();
    }

    public function getById(int $id): array
    {
        $item = $this->itemRepository->findById($id);
        if (!$item) {
            throw new \RuntimeException('Menu item non trovato.');
        }
        return $item;
    }

    /** Piatta, ordinata — costruire l'albero da parent_id è compito del chiamante (Theme: Cms::menu()). */
    public function getByMenu(int $menuId): array
    {
        $this->menuRepository->findById($menuId) ?: throw new \RuntimeException('Menu non trovato.');
        return $this->itemRepository->findByMenu($menuId);
    }

    public function create(array $data): int
    {
        $menuId = (int) ($data['menu_id'] ?? 0);
        if (!$this->menuRepository->findById($menuId)) {
            throw new \RuntimeException('Menu non trovato.');
        }

        $label = trim($data['label'] ?? '');
        if ($label === '') {
            throw new \RuntimeException('La label è obbligatoria.');
        }

        $target = $this->validateTarget($data);

        $parentId = null;
        if (!empty($data['parent_id'])) {
            $parentId = $this->validateParent($menuId, (int) $data['parent_id'], null);
        }

        return $this->itemRepository->create([
            'menu_id'         => $menuId,
            'parent_id'       => $parentId,
            'label'           => $label,
            'target_variant'  => $target['variant'],
            'target_entry_id' => $target['entry_id'],
            'target_url'      => $target['url'],
            'sort_order'      => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->getById($id);
        $fields   = [];

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
        if (array_key_exists('target_variant', $data) || array_key_exists('target_entry_id', $data) || array_key_exists('target_url', $data)) {
            $target = $this->validateTarget(array_merge($existing, $data));
            $fields['target_variant']  = $target['variant'];
            $fields['target_entry_id'] = $target['entry_id'];
            $fields['target_url']      = $target['url'];
        }
        if (array_key_exists('parent_id', $data)) {
            $fields['parent_id'] = $data['parent_id'] !== null
                ? $this->validateParent((int) $existing['menu_id'], (int) $data['parent_id'], $id)
                : null;
        }

        if ($fields !== []) {
            $this->itemRepository->update($id, $fields);
        }
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->itemRepository->delete($id);
    }

    /**
     * @return array{variant: string, entry_id: ?int, url: ?string}
     */
    private function validateTarget(array $data): array
    {
        $variant = $data['target_variant'] ?? null;
        if (!in_array($variant, self::KNOWN_VARIANTS, true)) {
            throw new \RuntimeException('target_variant non valido, atteso uno tra: ' . implode(', ', self::KNOWN_VARIANTS));
        }

        if ($variant === 'entry') {
            $entryId = $data['target_entry_id'] ?? null;
            if (!is_numeric($entryId) || (int) $entryId <= 0) {
                throw new \RuntimeException("Per target_variant 'entry', target_entry_id deve essere un id positivo.");
            }
            return ['variant' => 'entry', 'entry_id' => (int) $entryId, 'url' => null];
        }

        $url = trim($data['target_url'] ?? '');
        if ($url === '') {
            throw new \RuntimeException("Per target_variant '{$variant}', target_url è obbligatorio.");
        }

        return ['variant' => $variant, 'entry_id' => null, 'url' => $url];
    }

    /**
     * $currentId è null in creazione. In update, verifica che il nuovo
     * parent non sia il item stesso né un suo discendente — stesso
     * algoritmo di TaxonomyTermService::validateParent, qui scoped allo
     * stesso menu invece che alla stessa tassonomia.
     */
    private function validateParent(int $menuId, int $parentId, ?int $currentId): int
    {
        if ($currentId !== null && $parentId === $currentId) {
            throw new \RuntimeException('Un item non può essere parent di se stesso.');
        }

        $parent = $this->itemRepository->findById($parentId);
        if (!$parent) {
            throw new \RuntimeException('Il menu item parent indicato non esiste.');
        }
        if ((int) $parent['menu_id'] !== $menuId) {
            throw new \RuntimeException('Il menu item parent deve appartenere allo stesso menu.');
        }

        if ($currentId !== null) {
            $ancestor = $parent;
            while ($ancestor !== null) {
                if ((int) $ancestor['id'] === $currentId) {
                    throw new \RuntimeException('Assegnazione rifiutata: creerebbe un ciclo nella gerarchia del menu.');
                }
                $ancestor = $ancestor['parent_id'] !== null
                    ? $this->itemRepository->findById((int) $ancestor['parent_id']) ?: null
                    : null;
            }
        }

        return $parentId;
    }
}
