<?php

namespace Giga\Cms\Theme;

use Giga\Cms\Repositories\ContentEntryRepository;

/**
 * Un'istanza di blocco (Page Builder) nel Contratto Theme↔CMS. Stesso
 * principio di ContentEntryPresenter: fields[$key] uniforme, nessuna
 * proprietà magica sui Custom Field — qui non c'è nemmeno title/slug/
 * template (un blocco non ne ha), solo type e fields.
 */
class BlockPresenter
{
    private ?array $resolvedFields = null;

    public function __construct(
        private array $row,
        private array $language,
        private ContentEntryRepository $entryRepository,
        private FieldValueResolver $fieldValueResolver
    ) {
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'id'     => (int) $this->row['id'],
            'type'   => $this->row['block_type_slug'],
            'fields' => $this->resolveFields(),
            default  => throw new \RuntimeException(
                "Proprietà '{$name}' non esposta dal Contratto Theme↔CMS."
            ),
        };
    }

    private function resolveFields(): array
    {
        if ($this->resolvedFields !== null) {
            return $this->resolvedFields;
        }

        $values = $this->entryRepository->getBlockValues((int) $this->row['id']);

        return $this->resolvedFields = $this->fieldValueResolver->resolve($values, $this->language);
    }
}
