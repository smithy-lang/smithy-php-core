<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Factory for a format's `ShapeSerializerInterface` and
 * `ShapeDeserializerInterface` pair (JSON, CBOR, XML, etc.).
 *
 * A codec works with bytes, not protocol messages. Protocols use codecs to
 * encode and decode the request or response body.
 *
 * @internal Implementations are subject to change.
 */
abstract class AbstractCodec
{
    /**
     * Create a serializer for this codec's format.
     */
    abstract public function createSerializer(): ShapeSerializerInterface;

    /**
     * Create a deserializer for this codec's format.
     *
     * @param string $source The serialized data to deserialize from.
     */
    abstract public function createDeserializer(string $source): ShapeDeserializerInterface;
}
