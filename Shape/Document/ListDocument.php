<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding an ordered list of documents.
 *
 * Items may be heterogeneous when the document is untyped. Entries may also
 * be null. When a schema is attached, callers SHOULD supply items whose type
 * matches the schema's `listMember`, but the data-model does not enforce
 * homogeneity at construction.
 */
final class ListDocument extends AbstractDocument
{
    /**
     * @param list<AbstractDocument|null> $items Ordered member documents.
     * @param Schema|null $schema Schema to attach, or null for an untyped list.
     */
    public function __construct(
        public readonly array $items,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType List document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::List_;
    }

    /**
     * Return the list entries.
     *
     * @return list<AbstractDocument|null> Ordered member documents.
     */
    public function asList(): array
    {
        return $this->items;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeList()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeList(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->items,
            $idx,
            ShapeType::Document
        );
    }
}
