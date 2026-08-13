<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy float value.
 *
 * Widens to `asDouble()` unconditionally.
 */
class FloatDocument extends AbstractDocument
{
    public function __construct(
        public readonly float $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::Float_;
    }

    public function asFloat(): float
    {
        return $this->value;
    }

    public function asDouble(): float
    {
        return $this->value;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeFloat()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeFloat(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->value,
            $idx
        );
    }
}
