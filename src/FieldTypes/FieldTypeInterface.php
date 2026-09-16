<?php

namespace Giga\Cms\FieldTypes;

/**
 * Un Field Type è un componente autonomo con schema, validazione,
 * persistenza e rendering associati — il database non conosce la logica
 * specifica del tipo (Content Engine, "Custom Fields (Field Group) e Field
 * Type"). Aggiungere un tipo significa implementare questa interfaccia e
 * registrarlo in FieldTypeRegistry, senza toccare Content Engine,
 * Repository o migration.
 *
 * Nessuna dipendenza da Database qui per design: un Field Type valida e
 * trasforma un valore, non fa query. L'unico controllo che si appoggia al
 * DB (l'esistenza di un'entry referenziata da un field 'relation') è
 * lasciato alla FK di content_entry_values, non duplicato qui.
 */
interface FieldTypeInterface
{
    /** Chiave univoca usata in fields.type, es. 'text', 'number'. */
    public function key(): string;

    /**
     * Metadati statici del tipo.
     *
     * @return array{key: string, label: string, uses_value_json: bool}
     *   uses_value_json: true solo per i tipi che scrivono legittimamente
     *   su content_entry_values.value_json — strutture composite realmente
     *   non relazionali (Invariante #3). Falso per default: è l'eccezione,
     *   non la regola.
     */
    public function schema(): array;

    /**
     * Valida $rawValue (input grezzo, non ancora persistito) secondo le
     * regole del tipo e la config dichiarata sul field (fields.config,
     * es. le opzioni di una select).
     *
     * @throws \InvalidArgumentException se $rawValue non è valido
     */
    public function validate(mixed $rawValue, array $fieldConfig): void;

    /**
     * Converte $rawValue (già validato con validate()) nelle colonne di
     * content_entry_values da scrivere per una singola riga.
     *
     * @return array<string,mixed> es. ['value_text' => 'ciao']
     */
    public function persist(mixed $rawValue, array $fieldConfig): array;

    /**
     * Ricostruisce il valore di dominio da una riga già persistita
     * (operazione inversa di persist()), per il consumo lato tema/admin.
     */
    public function render(array $valueRow, array $fieldConfig): mixed;

    /**
     * Produce SOLO l'elemento di input grezzo (<input>/<select>/<textarea>)
     * per l'editing in admin — mai un wrapper: niente <label>, niente box
     * di errore, niente help text. Quello è responsabilità della view/
     * partial chiamante (decisione di design), non di questo metodo.
     *
     * name/id dell'elemento devono sempre coincidere con
     * $context->name/$context->id, mai inventati dall'implementazione.
     * Ogni valore stampato che deriva da $context->value o da $fieldConfig
     * (es. le option label di una select) va escapato esplicitamente — non
     * fidarsi di alcun escaping a monte.
     *
     * @throws \LogicException se il tipo non ha ancora un'implementazione
     */
    public function renderInput(FieldInputContext $context, array $fieldConfig): string;
}
