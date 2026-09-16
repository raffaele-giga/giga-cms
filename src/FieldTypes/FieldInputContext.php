<?php

namespace Giga\Cms\FieldTypes;

/**
 * Dati minimi che un Field Type serve per produrre il proprio
 * <input>/<select>/<textarea> grezzo in renderInput() — mai un wrapper
 * completo (label/error/help restano responsabilità della view/partial
 * chiamante, decisione di design: FieldTypeInterface produce solo
 * l'elemento di input, non il campo form intero).
 *
 * $value è nullable per design: un entry nuovo non ha ancora un valore
 * persistito per quel field — ogni Field Type decide internamente il
 * proprio default "vuoto" per quel caso (stringa vuota, nessuna opzione
 * selezionata, checkbox non spuntata...), non è responsabilità di questa
 * classe.
 */
final class FieldInputContext
{
    public function __construct(
        public readonly string $name,
        public readonly string $id,
        public readonly mixed $value,
        public readonly ?string $error,
    ) {}
}
