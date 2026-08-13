<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Reads from a wire format and produces shape field values.
 *
 * The deserializer parses the source, resolves each field to a member schema, and writes the parsed
 * value into a positional array by `memberIndex`. The caller pre-allocates the array and reads from
 * it by position after the call completes.
 *
 * @see AbstractCodec for creating deserializer instances.
 */
interface ShapeDeserializerInterface
{
    /**
     * Deserialize a structure's members from the wire format into `$members`.
     *
     * Writes each parsed value to `$members[$memberSchema->memberIndex]`. HTTP-binding
     * deserializers additionally handle headers, status code, prefix headers, and payload/body
     * delegation.
     *
     * @param Schema $schema Structure schema (with members) describing the shape being read.
     * @param array<int, mixed> $members Pre-allocated positional buffer sized to the shape's member
     *                   count. Modified in place, caller reads parsed values by member index after
     *                   this call returns.
     *
     * @return void
     */
    public function deserialize(Schema $schema, array &$members): void;
}
