<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding an explicit null value.
 *
 * Instances signal to a codec that the caller wants the null token in the wire output. Codecs
 * that emit an explicit null token detect a `NullDocument` via `instanceof` before dispatching
 * through the standard `serialize()` path.
 */
final class NullDocument extends AbstractDocument
{
    public function __construct(?Schema $schema = null)
    {
        parent::__construct($schema);
    }

    /**
     * Reports {@see ShapeType::Document} so callers reading `$doc->type()` see a document-typed
     * value regardless of the payload being null.
     */
    public function type(): ShapeType
    {
        return ShapeType::Document;
    }

    /** No-op. Callers dispatch on `instanceof NullDocument` before invoking this. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void {}
}
