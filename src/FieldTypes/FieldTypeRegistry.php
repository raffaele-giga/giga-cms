<?php

namespace Giga\Cms\FieldTypes;

class FieldTypeRegistry
{
    /** @var array<string, FieldTypeInterface> */
    private array $types = [];

    public function __construct()
    {
        foreach ($this->defaultTypes() as $type) {
            $this->register($type);
        }
    }

    public function register(FieldTypeInterface $type): void
    {
        $this->types[$type->key()] = $type;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): FieldTypeInterface
    {
        if (!$this->has($key)) {
            throw new \RuntimeException("Field Type '{$key}' non registrato.");
        }
        return $this->types[$key];
    }

    /**
     * 'repeater' resta fuori: content_entry_values copre solo cardinalità 1
     * (Decisione #1), un repeater è una struttura a cardinalità multipla
     * che non fa parte del contratto FieldTypeInterface (persist() produce
     * una sola riga). Si registrerà quando quel meccanismo sarà pronto —
     * non richiede modifiche qui oltre ad aggiungere la relativa entry.
     *
     * 'gallery' non sarà mai un FieldTypeInterface: è una struttura a
     * cardinalità multipla su content_entry_media (tabella dedicata),
     * gestita da ContentEntryService::getGallery()/replaceGallery(), non
     * da persist() su una colonna di content_entry_values.
     *
     * @return FieldTypeInterface[]
     */
    private function defaultTypes(): array
    {
        return [
            new TextFieldType(),
            new RichTextFieldType(),
            new NumberFieldType(),
            new DateFieldType(),
            new DateTimeFieldType(),
            new BooleanFieldType(),
            new SelectFieldType(),
            new RelationFieldType(),
            new LinkFieldType(),
            new MediaFieldType(),
        ];
    }
}
