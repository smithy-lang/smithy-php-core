<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;
use Smithy\Core\Shape\UnionInterface;

/**
 * Document wrapping a modeled union shape.
 *
 * Used when a service round-trips a tagged union through a document member. Wraps the generated
 * union so codecs serialize it through the standard union path while user code continues to see a
 * document.
 *
 * `$this->schema` is never null on a `UnionDocument`. The constructor falls back to the wrapped
 * value's `::schema()` when no schema is passed, so `serialize()` reads the property directly.
 */
final class UnionDocument extends AbstractDocument
{
    public function __construct(
        public readonly UnionInterface $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema ?? $value::schema());
    }

    public function type(): ShapeType
    {
        return ShapeType::Union;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeUnion()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeUnion(
            $this->schema,
            $this->value,
            $idx
        );
    }
}
