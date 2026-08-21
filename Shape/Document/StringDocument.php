<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\ShapeType;

/**
 * Document holding a Smithy string value.
 */
class StringDocument extends AbstractDocument
{
    /**
     * Build a string document.
     *
     * @param string $value String payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     */
    public function __construct(
        public readonly string $value,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType String document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::String_;
    }

    /**
     * Return the string payload.
     *
     * @return string String payload.
     */
    public function asString(): string
    {
        return $this->value;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeString()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeString(
            $this->schema ?? PreludeSchemas::for(ShapeType::String_),
            $this->value,
            $idx
        );
    }
}
