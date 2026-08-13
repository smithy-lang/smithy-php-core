<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy boolean value.
 */
final class BooleanDocument extends AbstractDocument
{
    public function __construct(
        public readonly bool $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::Boolean;
    }

    public function asBoolean(): bool
    {
        return $this->value;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeBoolean()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBoolean(
            $this->schema ?? PreludeSchemas::for(ShapeType::Boolean),
            $this->value,
            $idx
        );
    }
}
