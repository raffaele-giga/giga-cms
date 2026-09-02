<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\FieldTypes\FieldTypeRegistry;

/**
 * Permission-agnostic per design: nessun metodo qui chiama PermissionService
 * o AuditService. Enforcement dei permessi e audit logging sono
 * responsabilità del Controller del progetto consumer (stesso pattern già
 * in uso per UserService in giga-core/wos-pro — mai chiamati da un Service).
 * PermissionService in particolare è codice di progetto (App\Services\...,
 * diverso per ogni consumer): questo Service, condiviso da più consumer via
 * Composer, non può dipendervi. Vedi README.md di giga-cms.
 */
class ContentEntryService
{
    private ContentEntryRepository $entryRepository;
    private ContentTypeRepository $typeRepository;
    private ContentStatusRepository $statusRepository;
    private FieldRepository $fieldRepository;
    private FieldTypeRegistry $fieldTypeRegistry;

    public function __construct()
    {
        $this->entryRepository  = new ContentEntryRepository();
        $this->typeRepository   = new ContentTypeRepository();
        $this->statusRepository = new ContentStatusRepository();
        $this->fieldRepository  = new FieldRepository();
        $this->fieldTypeRegistry = new FieldTypeRegistry();
    }

    public function getById(int $id): array
    {
        $entry = $this->entryRepository->findById($id);
        if (!$entry) {
            throw new \RuntimeException('Content entry non trovata.');
        }
        return $entry;
    }

    public function getPaginated(int $contentTypeId, int $page, int $perPage, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = in_array($perPage, [10, 15, 25, 50]) ? $perPage : 15;

        $total      = $this->entryRepository->countAll($contentTypeId, $filters);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page       = min($page, $totalPages);
        $entries    = $this->entryRepository->findPaginated($contentTypeId, $page, $perPage, $filters);

        return [
            'entries'    => $entries,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * data accetta content_type_id oppure content_type_slug (uno dei due).
     * status_id di default risolto sullo stato di sistema 'draft' se assente.
     */
    public function create(array $data): int
    {
        $contentType = $this->resolveContentType($data);
        $statusId    = $this->resolveStatusId($data);

        return $this->entryRepository->create([
            'content_type_id'    => $contentType['id'],
            'status_id'          => $statusId,
            'published_at'       => $data['published_at']    ?? null,
            'published_until'    => $data['published_until'] ?? null,
            'include_in_archive' => isset($data['include_in_archive']) ? (int) (bool) $data['include_in_archive'] : 1,
            'indexable'          => isset($data['indexable']) ? (int) (bool) $data['indexable'] : 1,
            'sort_order'         => (int) ($data['sort_order'] ?? 0),
            'is_featured'        => isset($data['is_featured']) ? (int) (bool) $data['is_featured'] : 0,
            'template'           => $data['template'] ?? null,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $this->getById($id);

        $fields = array_intersect_key($data, array_flip([
            'status_id',
            'published_at',
            'published_until',
            'include_in_archive',
            'indexable',
            'sort_order',
            'is_featured',
            'template',
        ]));

        // PDO castiga i bool PHP a stringa prima del bind ((string) false === ''),
        // che MariaDB in strict mode rifiuta su una colonna BOOLEAN/TINYINT:
        // cast esplicito a 1/0, stesso pattern già in uso in UserRepository.
        foreach (['include_in_archive', 'indexable', 'is_featured'] as $boolField) {
            if (array_key_exists($boolField, $fields)) {
                $fields[$boolField] = (int) (bool) $fields[$boolField];
            }
        }

        $this->entryRepository->update($id, $fields);
    }

    public function delete(int $id): void
    {
        $this->getById($id);
        $this->entryRepository->delete($id);
    }

    public function restore(int $id): void
    {
        $this->entryRepository->restore($id);
    }

    public function getValues(int $entryId): array
    {
        return $this->entryRepository->getValues($entryId);
    }

    /**
     * Sostituisce i values di un'entry.
     *
     * value_json è validato per-field tramite il Field Type registry: una
     * riga può valorizzarlo solo se il tipo dichiarato del suo field lo
     * ammette esplicitamente (schema()['uses_value_json'], oggi solo
     * LinkFieldType — Invariante #3, "composito realmente non relazionale").
     *
     * RESTRIZIONE CONSERVATIVA residua, non più totale: se il field ha un
     * type non ancora coperto dal registry (es. un futuro 'media', prima
     * che Media/Gallery lo registri), scrivere value_json per QUEL field
     * specifico resta bloccato — non possiamo sapere se sarebbe legittimo.
     * Non blocca gli altri field della stessa chiamata, né chi non tocca
     * value_json affatto.
     */
    public function replaceValues(int $entryId, array $rows): void
    {
        foreach ($rows as $row) {
            $this->assertValueJsonAllowed($row);
        }

        $this->entryRepository->replaceValues($entryId, $rows);
    }

    private function assertValueJsonAllowed(array $row): void
    {
        if (!array_key_exists('value_json', $row) || $row['value_json'] === null) {
            return;
        }
        if (!array_key_exists('field_id', $row)) {
            throw new \RuntimeException('Impossibile validare value_json su una riga senza field_id.');
        }

        $fieldId = (int) $row['field_id'];
        $field   = $this->fieldRepository->findById($fieldId);
        if (!$field) {
            throw new \RuntimeException("Field id={$fieldId} non trovato.");
        }

        if (!$this->fieldTypeRegistry->has($field['type'])) {
            throw new \RuntimeException(
                "Scrittura su value_json non consentita per il field '{$field['key']}' (type "
                . "'{$field['type']}'): tipo non ancora coperto dal Field Type registry — restrizione "
                . 'conservativa su questo field specifico, non un blocco totale (vedi FieldTypeRegistry).'
            );
        }

        $schema = $this->fieldTypeRegistry->get($field['type'])->schema();
        if (empty($schema['uses_value_json'])) {
            throw new \RuntimeException(
                "Scrittura su value_json non consentita per il field '{$field['key']}' (type "
                . "'{$field['type']}'): questo tipo non lo dichiara come composito ammesso (Invariante #3)."
            );
        }
    }

    private function resolveContentType(array $data): array
    {
        if (!empty($data['content_type_id'])) {
            $type = $this->typeRepository->findById((int) $data['content_type_id']);
        } elseif (!empty($data['content_type_slug'])) {
            $type = $this->typeRepository->findBySlug($data['content_type_slug']);
        } else {
            throw new \RuntimeException('Specificare content_type_id o content_type_slug.');
        }

        if (!$type) {
            throw new \RuntimeException('Content Type non trovato.');
        }

        return $type;
    }

    private function resolveStatusId(array $data): int
    {
        if (!empty($data['status_id'])) {
            return (int) $data['status_id'];
        }

        $default = $this->statusRepository->findBySystemKey('draft');
        if (!$default) {
            throw new \RuntimeException("Stato di sistema 'draft' non trovato.");
        }

        return (int) $default['id'];
    }
}
