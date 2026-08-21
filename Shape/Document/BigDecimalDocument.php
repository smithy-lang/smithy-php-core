<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\ShapeType;
use Smithy\Core\Shape\BigDecimal;

/**
 * Document holding a Smithy bigDecimal value.
 *
 * Wraps a {@see BigDecimal} to preserve arbitrary decimal precision.
 */
final class BigDecimalDocument extends AbstractDocument
{
    /**
     * Build a bigDecimal document.
     *
     * @param BigDecimal $value Decimal payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     */
    public function __construct(
        public readonly BigDecimal $value,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType BigDecimal document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::BigDecimal;
    }

    /**
     * Return the wrapped decimal.
     *
     * @return BigDecimal Decimal payload.
     */
    public function asBigDecimal(): BigDecimal
    {
        return $this->value;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeBigDecimal()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBigDecimal(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->value,
            $idx
        );
    }
}
