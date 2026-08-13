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
    /**
     * Build a float document.
     *
     * @param float $value Float payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     */
    public function __construct(
        public readonly float $value,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Float document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Float_;
    }

    /**
     * Return the float payload.
     *
     * @return float Float payload.
     */
    public function asFloat(): float
    {
        return $this->value;
    }

    /**
     * Return the value widened to double.
     *
     * @return float Double payload.
     */
    public function asDouble(): float
    {
        return $this->value;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeFloat()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeFloat(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->value,
            $idx
        );
    }
}
