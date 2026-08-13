<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;
use Smithy\Core\Shape\BigInteger;

/**
 * Document holding a Smithy bigInteger value.
 *
 * Wraps a {@see BigInteger} to preserve precision beyond PHP's native `int`
 * range.
 */
final class BigIntegerDocument extends AbstractDocument
{
    public function __construct(
        public readonly BigInteger $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::BigInteger;
    }

    public function asBigInteger(): BigInteger
    {
        return $this->value;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeBigInteger()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBigInteger(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->value,
            $idx
        );
    }
}
