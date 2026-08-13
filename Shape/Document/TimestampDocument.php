<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy timestamp value.
 *
 * Stores POSIX seconds since the Unix epoch as a float. The fractional part
 * carries sub-second precision. The serializer resolves the wire format
 * (epoch-seconds, ISO 8601, HTTP date) from the attached schema's
 * `@timestampFormat` trait, falling back to the codec default.
 */
class TimestampDocument extends AbstractDocument
{
    public function __construct(
        public readonly float $epochSeconds,
        ?Schema $schema = null
    ) {
        parent::__construct($schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::Timestamp;
    }

    public function asTimestamp(): float
    {
        return $this->epochSeconds;
    }

    /** Dispatch into {@see ShapeSerializerInterface::serializeTimestamp()}. */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeTimestamp(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->epochSeconds,
            $idx
        );
    }
}
