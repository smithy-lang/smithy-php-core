<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy integer value.
 *
 * Widens to `asLong()` unconditionally. Narrows to `asShort()` and `asByte()`
 * only when the value fits the target range.
 */
class IntegerDocument extends AbstractDocument
{
    /**
     * Build an integer document.
     *
     * @param int $value Integer payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     */
    public function __construct(
        public readonly int $value,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Integer document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Integer;
    }

    /**
     * Return the integer payload.
     *
     * @return int Integer payload.
     */
    public function asInteger(): int
    {
        return $this->value;
    }

    /**
     * Return the value widened to long.
     *
     * @return int Long payload.
     */
    public function asLong(): int
    {
        return $this->value;
    }

    /**
     * @throws IncompatibleDocumentTypeException When the value is outside the signed 16-bit range.
     */
    public function asShort(): int
    {
        if ($this->value < -32768 || $this->value > 32767) {
            throw new IncompatibleDocumentTypeException(
                "Value {$this->value} does not fit in a short "
                    . '(16-bit signed integer).'
            );
        }

        return $this->value;
    }

    /**
     * @throws IncompatibleDocumentTypeException When the value is outside the signed 8-bit range.
     */
    public function asByte(): int
    {
        if ($this->value < -128 || $this->value > 127) {
            throw new IncompatibleDocumentTypeException(
                "Value {$this->value} does not fit in a byte "
                    . '(8-bit signed integer).'
            );
        }

        return $this->value;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeInteger()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeInteger(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->value,
            $idx
        );
    }
}
