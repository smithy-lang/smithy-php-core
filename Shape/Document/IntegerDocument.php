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
    public function __construct(
        public readonly int $value,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::Integer;
    }

    public function asInteger(): int
    {
        return $this->value;
    }

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

    /** Dispatch into {@see ShapeSerializerInterface::serializeInteger()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeInteger(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->value,
            $idx
        );
    }
}
