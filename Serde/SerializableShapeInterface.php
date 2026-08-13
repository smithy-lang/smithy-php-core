<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Marker for any generated shape that can drive a codec serializer.
 *
 * Structures, unions, enums, and documents all implement this interface. Each shape's
 * `serialize($s, $idx)` dispatches into the matching typed `serializeXxx()` on the encoder ( e.g.
 * `serializeStruct`, `serializeUnion`, `serializeString`/`serializeInteger` for enums, the document
 * subclass's own data-model dispatch).
 */
interface SerializableShapeInterface
{
    /**
     * Drive a codec serializer from this shape.
     *
     * @param ShapeSerializerInterface $serializer Encoder to write into.
     * @param int $idx Member index in the parent struct, used by codecs that route members
     *            positionally (HTTP bindings).
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void;
}
