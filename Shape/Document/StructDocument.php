<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document wrapping a modeled structure shape.
 *
 * Used when a service round-trips a modeled structure through a document member. Wraps the
 * generated structure so codecs serialize it through the standard struct path while user code
 * continues to see a document.
 *
 * `$this->schema` is never null on a `StructDocument`. The constructor falls back to the wrapped
 * value's `::schema()` when no schema is passed, so `serialize()` reads the property directly.
 */
final class StructDocument extends AbstractDocument
{
    public function __construct(
        public readonly SerializableStructInterface $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema ?? $value::schema());
    }

    public function type(): ShapeType
    {
        return ShapeType::Structure;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeStruct()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeStruct(
            $this->schema,
            $this->value,
            $idx
        );
    }
}
