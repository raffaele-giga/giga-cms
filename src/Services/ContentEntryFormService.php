<?php

namespace Giga\Cms\Services;

use Giga\Cms\FieldTypes\FieldTypeRegistry;

/**
 * Orchestrazione del form di editing di un'entry Content-Type-aware — lo
 * strato che manca tra il Field Type registry (data layer: persistenza/
 * validazione/lettura, completo e verificato) e un futuro Controller/view
 * generici. Non introduce nessuna logica nuova nei Field Type: chiama solo
 * i metodi già esistenti di FieldTypeInterface (validate/persist/render),
 * mai riscritti qui.
 *
 * Due responsabilità volutamente separate, mai mescolate:
 *  - loadSchema()/loadEntry(): lettura (nessuna scrittura, nessuna
 *    validazione).
 *  - validate()/save(): scrittura, con save() che NON richiama validate()
 *    al proprio interno — è responsabilità del chiamante (il futuro
 *    controller) validare prima e chiamare save() solo se l'array errori
 *    è vuoto. Se save() viene chiamato con dati non validati, un
 *    \InvalidArgumentException lanciato da persist() si propaga senza
 *    essere intercettato: fail loud, non un errore silenzioso.
 */
class ContentEntryFormService
{
    private ContentTypeService $typeService;
    private FieldGroupService $groupService;
    private ContentEntryService $entryService;
    private FieldTypeRegistry $fieldTypeRegistry;

    public function __construct()
    {
        $this->typeService       = new ContentTypeService();
        $this->groupService      = new FieldGroupService();
        $this->entryService      = new ContentEntryService();
        $this->fieldTypeRegistry = new FieldTypeRegistry();
    }

    /**
     * Schema del form per un Content Type — nessun valore di entry qui
     * (vedi loadEntry() per quello). Struttura:
     *
     * [
     *   'content_type' => [...],
     *   'field_groups' => [
     *     ['group' => [...], 'fields' => [
     *       ['field' => [...], 'type_instance' => FieldTypeInterface],
     *       ...
     *     ]],
     *     ...
     *   ],
     * ]
     *
     * $field['config'] arriva già decodificato (json_decode) — a
     * differenza di FieldGroupService::getFields()/ContentTypeRepository,
     * che ritornano la colonna `config` come stringa JSON grezza (solo
     * FieldService::getById()/getAll() la decodificano, e qui non li
     * usiamo perché getFields() già fa il join con field_group_fields).
     *
     * Per i field 'relation': $field['relation_options'] viene iniettato
     * qui, popolato con un'euristica pragmatica per V1 (non una soluzione
     * generale): per ogni content_type target REFERENZIATO DA ALMENO UN
     * field 'relation' di questo schema, una sola query di risoluzione
     * (mai una per campo, anche se più field puntano allo stesso target —
     * risultato cachato per la durata della chiamata). La label di ogni
     * opzione è il valore del PRIMO field di tipo 'text' trovato nei
     * Field Group del content type target (per 'client' è 'name') — se il
     * target non ha nessun field 'text', fallback a "#{id}". Questo è lo
     * stesso limite già noto della prima versione di
     * RelationFieldType::renderInput() (quando risolveva da sé le
     * opzioni), ora isolato in un solo punto invece che duplicato in ogni
     * Field Type che ne avesse bisogno.
     *
     * @throws \RuntimeException se il Content Type non esiste
     */
    public function loadSchema(string $typeSlug): array
    {
        try {
            $contentType = $this->typeService->getBySlug($typeSlug);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Content Type '{$typeSlug}' non trovato.", previous: $e);
        }

        // Enforcement qui, non dentro getBySlug(): quel metodo deve restare
        // neutro (la UI di gestione /admin/content-types lo usa anche per i
        // Content Type disabilitati, per poterli riattivare). loadSchema() è
        // l'unico punto di ingresso condiviso da tutti i metodi di
        // ContentEntryController (index/create/store/edit/update, verificato
        // in audit precedente) — un controllo qui basta a bloccare l'accesso
        // via URL diretto senza doverlo duplicare altrove.
        if ((int) $contentType['admin_enabled'] !== 1) {
            throw new \RuntimeException("Content Type '{$typeSlug}' non è abilitato.");
        }

        $relationOptionsByTarget = [];
        $fieldGroups = [];

        foreach ($this->typeService->getFieldGroups($contentType['id']) as $group) {
            $fields = [];

            foreach ($this->groupService->getFields((int) $group['id']) as $field) {
                $field['config'] = $field['config'] !== null ? json_decode($field['config'], true) : null;

                if ($field['type'] === 'relation') {
                    $targetSlug = $field['config']['target_content_type'] ?? null;

                    if (is_string($targetSlug) && $targetSlug !== '') {
                        if (!array_key_exists($targetSlug, $relationOptionsByTarget)) {
                            $relationOptionsByTarget[$targetSlug] = $this->resolveRelationOptions($targetSlug);
                        }
                        $field['relation_options'] = $relationOptionsByTarget[$targetSlug];
                    } else {
                        $field['relation_options'] = [];
                    }
                }

                $fields[] = [
                    'field'         => $field,
                    'type_instance' => $this->fieldTypeRegistry->get($field['type']),
                ];
            }

            $fieldGroups[] = ['group' => $group, 'fields' => $fields];
        }

        return ['content_type' => $contentType, 'field_groups' => $fieldGroups];
    }

    /**
     * Valori persistiti di un'entry specifica, ricostruiti via
     * FieldTypeInterface::render() di ciascun tipo (riuso, non riscritto).
     * Ritorna ['field_key' => valore_ricostruito, ...], un campo assente
     * dalle righe persistite (mai valorizzato) diventa null.
     *
     * @throws \RuntimeException se l'entry non esiste o non appartiene al
     *                           Content Type indicato da $typeSlug
     */
    public function loadEntry(string $typeSlug, int $entryId): array
    {
        $schema = $this->loadSchema($typeSlug);

        try {
            $entry = $this->entryService->getById($entryId);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException("Entry id={$entryId} non trovata.", previous: $e);
        }

        if ((int) $entry['content_type_id'] !== (int) $schema['content_type']['id']) {
            throw new \RuntimeException(
                "Entry id={$entryId} non appartiene al Content Type '{$typeSlug}'."
            );
        }

        $rowsByFieldId = [];
        foreach ($this->entryService->getValues($entryId) as $row) {
            $rowsByFieldId[(int) $row['field_id']] = $row;
        }

        $values = [];
        foreach ($schema['field_groups'] as $group) {
            foreach ($group['fields'] as $entryField) {
                $field        = $entryField['field'];
                $typeInstance = $entryField['type_instance'];
                $row          = $rowsByFieldId[(int) $field['id']] ?? null;

                $values[$field['key']] = $row !== null
                    ? $typeInstance->render($row, $field['config'] ?? [])
                    : null;
            }
        }

        return $values;
    }

    /**
     * Valida $postData contro lo schema — un solo giro su tutti i campi,
     * nessuno short-circuit al primo errore: ogni field invalido finisce
     * nell'array ritornato. NON lancia mai: un array vuoto significa "tutto
     * valido", un array non vuoto ['field_key' => 'messaggio'] gli errori
     * da mostrare. Nessuna modifica a FieldTypeInterface::validate() —
     * resta invariata su tutti i 10 tipi.
     *
     * Enforcement di `fields.required` (Opzione A, decisione presa in
     * sessione): applicata QUI, non in nessuno dei 10 FieldTypeInterface —
     * quelli restano invariati, nessuno di loro legge quella colonna.
     * `$field['required']` è la colonna reale della riga `fields` (un
     * intero booleano, sibling di `config`), non una chiave dentro
     * `config` — `config` resta metadato di definizione del tipo (opzioni
     * select, min/max...), `required` è una proprietà del field stessa,
     * già esposta separatamente da FieldGroupService::getFields()/
     * loadSchema() come `$field['required']`.
     *
     * Eccezione esplicita: 'boolean' non è mai soggetto a required, anche
     * se la colonna lo dichiara — una checkbox non spuntata è il valore
     * legittimo `false`, non un "campo mancante" (V1, decisione presa).
     *
     * "Vuoto" qui è null o stringa fatta di soli spazi/vuota — non un
     * empty() diretto su $rawValue: un Number/id a "0" o l'intero 0 non
     * sono "vuoti" nel senso di questo controllo (0 può essere un valore
     * legittimo), solo l'assenza reale del dato lo è.
     *
     * Un campo required-e-vuoto NON arriva a
     * FieldTypeInterface::validate(): non ha senso validare il formato di
     * un valore assente, e alcuni tipi (es. NumberFieldType con null)
     * lancerebbero comunque un errore di formato fuorviante ("deve essere
     * numerico") al posto di quello corretto ("obbligatorio").
     */
    public function validate(array $schema, array $postData): array
    {
        $errors = [];

        foreach ($schema['field_groups'] as $group) {
            foreach ($group['fields'] as $entryField) {
                $field        = $entryField['field'];
                $typeInstance = $entryField['type_instance'];
                $rawValue     = $postData[$field['key']] ?? null;

                $isRequired = !empty($field['required']) && $field['type'] !== 'boolean';
                if ($isRequired && $this->isEmptyValue($rawValue)) {
                    $errors[$field['key']] = 'Campo obbligatorio.';
                    continue;
                }

                try {
                    $typeInstance->validate($rawValue, $field['config'] ?? []);
                } catch (\InvalidArgumentException $e) {
                    $errors[$field['key']] = $e->getMessage();
                }
            }
        }

        return $errors;
    }

    /**
     * Crea (se $entryId è null) o riusa un'entry, poi sostituisce TUTTI i
     * suoi values in un'unica chiamata a ContentEntryService::replaceValues()
     * (le righe di tutti i campi raccolte prima, mai una replaceValues()
     * per campo). Ritorna l'id dell'entry.
     *
     * NON chiama validate(): è responsabilità del chiamante farlo prima e
     * invocare save() solo se l'array errori è vuoto. Un
     * \InvalidArgumentException lanciato da persist() (dati non validati
     * passati qui comunque) si propaga senza essere intercettato.
     */
    public function save(array $schema, array $postData, ?int $entryId): int
    {
        if ($entryId === null) {
            $entryId = $this->entryService->create([
                'content_type_slug' => $schema['content_type']['slug'],
            ]);
        }

        $rows = [];
        foreach ($schema['field_groups'] as $group) {
            foreach ($group['fields'] as $entryField) {
                $field        = $entryField['field'];
                $typeInstance = $entryField['type_instance'];
                $rawValue     = $postData[$field['key']] ?? null;

                $rows[] = array_merge(
                    ['field_id' => (int) $field['id']],
                    $typeInstance->persist($rawValue, $field['config'] ?? [])
                );
            }
        }

        $this->entryService->replaceValues($entryId, $rows);

        return $entryId;
    }

    /**
     * Risolve le opzioni di un field 'relation' verso $targetSlug — una
     * query per il Content Type target, una per il Field Group/i suoi
     * field (per trovare il field 'text' da usare come label), una per
     * l'elenco entry, e (limite noto, accettato per V1: nessun metodo
     * batch "values di più entry insieme" disponibile oggi su
     * ContentEntryService) una query values per singola entry per leggere
     * la label.
     */
    private function resolveRelationOptions(string $targetSlug): array
    {
        try {
            $targetType = $this->typeService->getBySlug($targetSlug);
        } catch (\RuntimeException) {
            // target_content_type configurato ma il Content Type non esiste
            // (più) davvero — nessuna opzione disponibile, non un errore
            // fatale per l'intero schema.
            return [];
        }

        $labelField = $this->resolveLabelField((int) $targetType['id']);
        $entries    = $this->entryService->getPaginated((int) $targetType['id'], 1, 100)['entries'];
        $textType   = $this->fieldTypeRegistry->get('text');

        $options = [];
        foreach ($entries as $entry) {
            $entryId = (int) $entry['id'];
            $label   = null;

            if ($labelField !== null) {
                foreach ($this->entryService->getValues($entryId) as $row) {
                    if ((int) $row['field_id'] === (int) $labelField['id']) {
                        $label = $textType->render($row, []);
                        break;
                    }
                }
            }

            $options[] = [
                'id'    => $entryId,
                'label' => $label !== null && $label !== '' ? $label : "#{$entryId}",
            ];
        }

        return $options;
    }

    /**
     * Id del field 'text' da usare come nome rappresentativo delle entry
     * di questo Content Type nelle liste admin (content-entries/index.php)
     * — stessa logica già usata per le opzioni Relation
     * (resolveRelationOptions()), qui applicata al Content Type stesso
     * invece che a un target di relazione: resolveLabelField() era già
     * generico (accetta un contentTypeId, non uno specifico a "relation
     * target"), nessun refactor necessario, solo un ingresso pubblico in
     * più. Da chiamare una sola volta per request (non per riga), il
     * chiamante tipico è ContentEntryController::index().
     */
    public function resolveLabelFieldId(string $typeSlug): ?int
    {
        $contentType = $this->typeService->getBySlug($typeSlug);
        $labelField  = $this->resolveLabelField((int) $contentType['id']);

        return $labelField !== null ? (int) $labelField['id'] : null;
    }

    /** Primo field di tipo 'text' trovato nei Field Group del content type, o null se nessuno esiste. */
    private function resolveLabelField(int $contentTypeId): ?array
    {
        foreach ($this->typeService->getFieldGroups($contentTypeId) as $group) {
            foreach ($this->groupService->getFields((int) $group['id']) as $field) {
                if ($field['type'] === 'text') {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * "Vuoto" per l'enforcement di required: null o stringa vuota/di soli
     * spazi. Deliberatamente NON un empty() diretto — "0"/0/0.0/false sono
     * valori legittimi per Number/Boolean, non un campo mancante.
     */
    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            return true;
        }
        return false;
    }
}
