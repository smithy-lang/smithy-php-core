<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy string value.
 */
class StringDocument extends AbstractDocument
{
    public function __construct(
        public readonly string $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::String_;
    }

    public function asString(): string
    {
        return $this->value;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeString()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeString(
            $this->schema ?? PreludeSchemas::for(ShapeType::String_),
            $this->value,
            $idx
        );
    }
}
