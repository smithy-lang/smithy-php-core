<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy blob value as raw bytes.
 */
class BlobDocument extends AbstractDocument
{
    public function __construct(
        public readonly string $bytes,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::Blob;
    }

    public function asBlob(): string
    {
        return $this->bytes;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeBlob()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBlob(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->bytes,
            $idx
        );
    }
}
