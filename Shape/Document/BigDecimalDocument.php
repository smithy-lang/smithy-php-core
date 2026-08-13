<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;
use Smithy\Core\Shape\BigDecimal;

/**
 * Document holding a Smithy bigDecimal value.
 *
 * Wraps a {@see BigDecimal} to preserve arbitrary decimal precision.
 */
final class BigDecimalDocument extends AbstractDocument
{
    public function __construct(
        public readonly BigDecimal $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::BigDecimal;
    }

    public function asBigDecimal(): BigDecimal
    {
        return $this->value;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeBigDecimal()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBigDecimal(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->value,
            $idx
        );
    }
}
