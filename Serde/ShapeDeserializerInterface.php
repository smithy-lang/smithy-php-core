<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Reads from a wire format and produces shape field values.
 *
 * The deserializer parses the source, resolves each field to a member schema,
 * and writes the parsed value into a positional array by `memberIndex`. The
 * caller pre-allocates the array and reads from it by position after the call
 * completes.
 *
 * @see AbstractCodec for creating deserializer instances.
 */
interface ShapeDeserializerInterface
{
    /**
     * Deserialize a structure's members from the wire format into `$members`.
     *
     * Writes each parsed value to `$members[$memberSchema->memberIndex]`.
     * HTTP-binding deserializers additionally handle headers, status code,
     * prefix headers, and payload/body delegation.
     *
     * @param Schema $schema The structure schema (with members)
     * @param array &$members Pre-allocated array sized to member count (integer-indexed)
     */
    public function deserialize(Schema $schema, array &$members): void;
}
