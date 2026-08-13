<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Factory for a format's `ShapeSerializerInterface` and `ShapeDeserializerInterface` pair (JSON,
 * CBOR, XML, etc.).
 *
 * A codec works with bytes. Protocols use codecs to encode and decode the
 * request or response body.
 *
 * @internal Implementations are subject to change.
 */
abstract class AbstractCodec
{
    /**
     * Create a serializer for this codec's format.
     *
     * @return ShapeSerializerInterface Fresh serializer instance ready to receive `serializeXxx`
     *                                  calls.
     */
    abstract public function createSerializer(): ShapeSerializerInterface;

    /**
     * Create a deserializer for this codec's format.
     *
     * @param string $source Serialized bytes to deserialize from.
     *
     * @return ShapeDeserializerInterface Fresh deserializer bound to `$source`.
     */
    abstract public function createDeserializer(string $source): ShapeDeserializerInterface;
}
