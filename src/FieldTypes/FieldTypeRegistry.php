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
     * Tipi coperti in questo giro: quelli che non richiedono Asset Library
     * (immagine/media resta fuori) né una relazione a cardinalità multipla
     * (repeater resta fuori — content_entry_values copre solo cardinalità 1,
     * Decisione #1). Si registreranno quando i rispettivi sottosistemi
     * (Media/Gallery, Repeater) saranno pronti — non richiedono modifiche
     * qui oltre ad aggiungere la relativa entry a questa lista.
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
        ];
    }
}
