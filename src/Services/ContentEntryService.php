<?php

namespace Giga\Cms\Services;

use Giga\Cms\Repositories\ContentEntryRepository;
use Giga\Cms\Repositories\ContentTypeRepository;
use Giga\Cms\Repositories\ContentStatusRepository;
use Giga\Cms\Repositories\FieldRepository;
use Giga\Cms\Repositories\TaxonomyTermRepository;
use Giga\Cms\Repositories\ContentEntryBlockRepository;
use Giga\Cms\Repositories\BlockTypeRepository;
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
    private TaxonomyTermRepository $termRepository;
    private ContentEntryBlockRepository $blockRepository;
    private BlockTypeRepository $blockTypeRepository;
    private FieldTypeRegistry $fieldTypeRegistry;

    public function __construct()
    {
        $this->entryRepository    = new ContentEntryRepository();
        $this->typeRepository     = new ContentTypeRepository();
        $this->statusRepository   = new ContentStatusRepository();
        $this->fieldRepository    = new FieldRepository();
        $this->termRepository     = new TaxonomyTermRepository();
        $this->blockRepository    = new ContentEntryBlockRepository();
        $this->blockTypeRepository = new BlockTypeRepository();
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
        $template    = $data['template'] ?? null;
        $this->validateTemplateChoice($template, $contentType);

        return $this->entryRepository->create([
            'content_type_id'    => $contentType['id'],
            'status_id'          => $statusId,
            'published_at'       => $data['published_at']    ?? null,
            'published_until'    => $data['published_until'] ?? null,
            'include_in_archive' => isset($data['include_in_archive']) ? (int) (bool) $data['include_in_archive'] : 1,
            'indexable'          => isset($data['indexable']) ? (int) (bool) $data['indexable'] : 1,
            'follow'             => isset($data['follow']) ? (int) (bool) $data['follow'] : 1,
            'sort_order'         => (int) ($data['sort_order'] ?? 0),
            'is_featured'        => isset($data['is_featured']) ? (int) (bool) $data['is_featured'] : 0,
            'template'           => $template,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $entry = $this->getById($id);

        $fields = array_intersect_key($data, array_flip([
            'status_id',
            'published_at',
            'published_until',
            'include_in_archive',
            'indexable',
            'follow',
            'sort_order',
            'is_featured',
            'template',
        ]));

        // PDO castiga i bool PHP a stringa prima del bind ((string) false === ''),
        // che MariaDB in strict mode rifiuta su una colonna BOOLEAN/TINYINT:
        // cast esplicito a 1/0, stesso pattern già in uso in UserRepository.
        foreach (['include_in_archive', 'indexable', 'follow', 'is_featured'] as $boolField) {
            if (array_key_exists($boolField, $fields)) {
                $fields[$boolField] = (int) (bool) $fields[$boolField];
            }
        }

        if (array_key_exists('template', $fields)) {
            $contentType = $this->typeRepository->findById((int) $entry['content_type_id']);
            $this->validateTemplateChoice($fields['template'], $contentType);
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

    /**
     * Niente stato "scheduled" separato: la programmazione è status=published
     * con published_at nel futuro (Content Engine, Content Entry). Se
     * $publishedAt è null usa NOW() — pubblicazione immediata.
     */
    public function publish(int $id, ?string $publishedAt = null): void
    {
        $this->getById($id);
        $status = $this->resolveStatusBySystemKey('published');

        $this->entryRepository->update($id, [
            'status_id'    => $status['id'],
            'published_at' => $publishedAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    /** Torna a draft. Non tocca published_at/published_until: restano per riferimento/riuso a una ripubblicazione. */
    public function unpublish(int $id): void
    {
        $this->getById($id);
        $status = $this->resolveStatusBySystemKey('draft');
        $this->entryRepository->update($id, ['status_id' => $status['id']]);
    }

    public function archive(int $id): void
    {
        $this->getById($id);
        $status = $this->resolveStatusBySystemKey('archived');
        $this->entryRepository->update($id, ['status_id' => $status['id']]);
    }

    /**
     * Pubblico effettivo per un'entry già caricata (findById()/findPaginated(),
     * che selezionano già status_is_public/published_at/published_until —
     * nessuna query aggiuntiva qui). Stessa regola di
     * ContentEntryRepository::effectivePublicCondition(), espressa in PHP:
     * le due non condividono un'implementazione letterale (SQL e PHP sono
     * runtime diversi) ma sono verificate allineate da
     * dev/bin/smoke_test_lifecycle.php — se cambi questa, cambia anche quella.
     */
    public function isEffectivelyPublic(array $entry): bool
    {
        if (empty($entry['status_is_public']) || empty($entry['published_at'])) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if (new \DateTimeImmutable($entry['published_at']) > $now) {
            return false;
        }
        if (!empty($entry['published_until']) && new \DateTimeImmutable($entry['published_until']) <= $now) {
            return false;
        }

        return true;
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
            $this->assertRowValid($row);
        }

        $this->entryRepository->replaceValues($entryId, $rows);
    }

    /**
     * Un solo lookup del field per riga, usato da entrambi i controlli
     * (value_json ammesso, coerenza translatable/language_id) — evita due
     * query separate e la duplicazione del "field non trovato".
     */
    private function assertRowValid(array $row): void
    {
        $touchesValueJson = array_key_exists('value_json', $row) && $row['value_json'] !== null;
        $hasLanguage      = array_key_exists('language_id', $row) && $row['language_id'] !== null;

        if (!$touchesValueJson && !array_key_exists('field_id', $row)) {
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

        if ($touchesValueJson) {
            $this->assertValueJsonAllowed($field);
        }

        // Modello multilingua: language_id è il discriminatore per i valori
        // traducibili — deve essere presente se e solo se il field è
        // translatable (altrimenti un field globale finirebbe duplicato per
        // lingua, o uno translatable perderebbe la distinzione per lingua).
        if ($field['translatable'] && !$hasLanguage) {
            throw new \RuntimeException(
                "Il field '{$field['key']}' è translatable: language_id è obbligatorio su questa riga."
            );
        }
        if (!$field['translatable'] && $hasLanguage) {
            throw new \RuntimeException(
                "Il field '{$field['key']}' non è translatable: language_id non è ammesso su questa riga."
            );
        }
    }

    private function assertValueJsonAllowed(array $field): void
    {
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

    public function getGallery(int $entryId, int $fieldId): array
    {
        return $this->entryRepository->getGallery($entryId, $fieldId);
    }

    /**
     * Sostituisce l'intera gallery di un field su un'entry. Il field deve
     * essere dichiarato type='gallery' — distinto da 'media' (riferimento
     * singolo, cardinalità 1 via value_media_id/MediaFieldType). 'gallery'
     * non è un Field Type nel registry: non produce una riga di
     * content_entry_values (persist() lavora su una colonna, la gallery è
     * una struttura a cardinalità multipla su una tabella dedicata,
     * content_entry_media — Decisione #1).
     *
     * L'esistenza di ogni media_id non è verificata qui: la FK di
     * content_entry_media.media_id lo garantisce già a livello DB.
     *
     * @param array<int, array{media_id:int, caption?:?string}> $items
     */
    public function replaceGallery(int $entryId, int $fieldId, array $items): void
    {
        $field = $this->fieldRepository->findById($fieldId);
        if (!$field) {
            throw new \RuntimeException("Field id={$fieldId} non trovato.");
        }
        if ($field['type'] !== 'gallery') {
            throw new \RuntimeException(
                "Il field '{$field['key']}' non è di tipo 'gallery' (è '{$field['type']}')."
            );
        }

        $this->entryRepository->replaceGallery($entryId, $fieldId, $items);
    }

    public function getTerms(int $entryId, int $taxonomyId): array
    {
        return $this->entryRepository->getTerms($entryId, $taxonomyId);
    }

    /**
     * Sostituisce i termini di UNA tassonomia sull'entry (le altre
     * tassonomie assegnate alla stessa entry non vengono toccate). Valida
     * che la tassonomia sia effettivamente assegnata al Content Type
     * dell'entry (content_type_taxonomies) e che ogni termine appartenga
     * a quella tassonomia — un'entry non può avere un termine "Manufacturing"
     * (Industry) se il suo Content Type non usa la tassonomia Industry.
     */
    public function syncTerms(int $entryId, int $taxonomyId, array $termIds): void
    {
        $entry = $this->getById($entryId);

        $assignedTaxonomyIds = array_map(
            fn(array $t) => (int) $t['id'],
            $this->typeRepository->getTaxonomies((int) $entry['content_type_id'])
        );
        if (!in_array($taxonomyId, $assignedTaxonomyIds, true)) {
            throw new \RuntimeException(
                "La tassonomia id={$taxonomyId} non è assegnata al Content Type di questa entry."
            );
        }

        foreach ($termIds as $termId) {
            $term = $this->termRepository->findById((int) $termId);
            if (!$term) {
                throw new \RuntimeException("Termine id={$termId} non trovato.");
            }
            if ((int) $term['taxonomy_id'] !== $taxonomyId) {
                throw new \RuntimeException("Il termine id={$termId} non appartiene alla tassonomia id={$taxonomyId}.");
            }
        }

        $this->entryRepository->replaceTerms($entryId, $taxonomyId, array_map('intval', $termIds));
    }

    /** Relazioni uscenti di un tipo da un'entry. */
    public function getRelations(int $entryId, string $relationType): array
    {
        return $this->entryRepository->getRelations($entryId, $relationType);
    }

    /**
     * Relazione inversa (Invariante #8) — chi ha una relazione $relationType
     * verso questa entry. Sempre una query, mai una riga da sincronizzare.
     */
    public function getInverseRelations(int $entryId, string $relationType): array
    {
        return $this->entryRepository->getInverseRelations($entryId, $relationType);
    }

    /**
     * Sostituisce le relazioni uscenti di UN relation_type dall'entry (gli
     * altri relation_type sulla stessa entry non vengono toccati).
     * L'esistenza di ogni entry correlata non è verificata qui: la FK di
     * content_entry_relations.related_entry_id lo garantisce già a livello
     * DB. relation_type non è validato contro un vocabolario: è una
     * stringa libera (Content Engine, "Relazioni") — nessuna tabella di
     * definizione dei tipi di relazione esiste nello schema.
     */
    public function replaceRelations(int $entryId, string $relationType, array $relatedEntryIds): void
    {
        $this->getById($entryId);

        if (in_array($entryId, array_map('intval', $relatedEntryIds), true)) {
            throw new \RuntimeException("Un'entry non può avere una relazione verso se stessa.");
        }

        $this->entryRepository->replaceRelations($entryId, $relationType, array_map('intval', $relatedEntryIds));
    }

    /**
     * Nessun vincolo se il Content Type non dichiara template_options
     * (Template per Content Type) o se non si sta impostando un template.
     */
    private function validateTemplateChoice(?string $template, array $contentType): void
    {
        if ($template === null || empty($contentType['template_options'])) {
            return;
        }

        $options = json_decode($contentType['template_options'], true);
        if (is_array($options) && !in_array($template, $options, true)) {
            throw new \RuntimeException(
                "template '{$template}' non è tra le varianti dichiarate dal Content Type: " . implode(', ', $options)
            );
        }
    }

    /** Istanze di blocco dell'entry, ordinate (Page Builder). */
    public function getBlocks(int $entryId): array
    {
        $this->getById($entryId);
        return $this->blockRepository->findByEntry($entryId);
    }

    /**
     * Aggiunge un'istanza di Block Type in coda (o alla posizione
     * $sortOrder indicata). Non è un "replace": ogni blocco ha identità
     * stabile (i suoi content_entry_values scoped per block_id) — vedi
     * ContentEntryBlockRepository.
     */
    public function addBlock(int $entryId, int $blockTypeId, ?int $sortOrder = null): int
    {
        $this->getById($entryId);
        if (!$this->blockTypeRepository->findById($blockTypeId)) {
            throw new \RuntimeException("Block Type id={$blockTypeId} non trovato.");
        }

        $sortOrder ??= count($this->blockRepository->findByEntry($entryId));

        return $this->blockRepository->create([
            'entry_id'      => $entryId,
            'block_type_id' => $blockTypeId,
            'sort_order'    => $sortOrder,
        ]);
    }

    /** Riordina le istanze esistenti (aggiorna sort_order, non le ricrea) — vedi ContentEntryBlockRepository::reorder(). */
    public function reorderBlocks(int $entryId, array $blockIds): void
    {
        $this->getById($entryId);

        foreach ($blockIds as $blockId) {
            $block = $this->blockRepository->findById((int) $blockId);
            if (!$block || (int) $block['entry_id'] !== $entryId) {
                throw new \RuntimeException("Block id={$blockId} non appartiene a questa entry.");
            }
        }

        $this->blockRepository->reorder($entryId, array_map('intval', $blockIds));
    }

    /** Cancella l'istanza di blocco — FK CASCADE su content_entry_values.block_id ne ripulisce i values. */
    public function removeBlock(int $blockId): void
    {
        if (!$this->blockRepository->findById($blockId)) {
            throw new \RuntimeException('Block non trovato.');
        }
        $this->blockRepository->delete($blockId);
    }

    public function getBlockValues(int $blockId): array
    {
        if (!$this->blockRepository->findById($blockId)) {
            throw new \RuntimeException('Block non trovato.');
        }
        return $this->entryRepository->getBlockValues($blockId);
    }

    /**
     * Stessa validazione per-riga di replaceValues() (assertRowValid,
     * riusata non duplicata): value_json ammesso solo se il tipo del
     * field lo dichiara, language_id obbligatorio solo se il field è
     * translatable. Lo scoping per block_id lo fa il Repository.
     */
    public function replaceBlockValues(int $blockId, array $rows): void
    {
        $block = $this->blockRepository->findById($blockId);
        if (!$block) {
            throw new \RuntimeException('Block non trovato.');
        }

        foreach ($rows as $row) {
            $this->assertRowValid($row);
        }

        $this->entryRepository->replaceBlockValues((int) $block['entry_id'], $blockId, $rows);
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

        return (int) $this->resolveStatusBySystemKey('draft')['id'];
    }

    private function resolveStatusBySystemKey(string $systemKey): array
    {
        $status = $this->statusRepository->findBySystemKey($systemKey);
        if (!$status) {
            throw new \RuntimeException("Stato di sistema '{$systemKey}' non trovato.");
        }
        return $status;
    }
}
