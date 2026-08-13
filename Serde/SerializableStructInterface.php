<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Contract for generated structure shapes. Error shapes are structures with the `@error` trait, and
 * implement this too.
 *
 * Adds the struct-specific surface on top of {@see SerializableShapeInterface}: the shape's
 * `Schema`, the codec-driven member pushdown (`serializeMembers`), and positional member access
 * (`getMemberValue`).
 */
interface SerializableStructInterface extends SerializableShapeInterface
{
    /**
     * Return the schema describing this shape's structure.
     *
     * Implementations cache the Schema instance (lazy-static pattern) so it is constructed once per
     * process.
     *
     * @return Schema Runtime schema for this shape.
     */
    public static function schema(): Schema;

    /**
     * Push this struct's non-null members into the serializer.
     *
     * Called by the codec from inside `serializeStruct()` after the codec has opened the struct
     * frame. Generated implementations emit one `$serializer->serializeXxx($schema, $value, $idx)`
     * call per non-null member, with attributes ordered before elements.
     *
     * @param ShapeSerializerInterface $serializer Codec-supplied writer to push non-null members
     *                                 into.
     *
     * @return void
     */
    public function serializeMembers(ShapeSerializerInterface $serializer): void;

    /**
     * Return a member's value by its schema (positional dispatch).
     *
     * Generated as a `match` expression on `$member->memberIndex` returning the matching property.
     * Returns null for unset optional members. Used for filtered-schema serialization (body-only
     * members).
     *
     * @param Schema $member Member schema to look up.
     *
     * @return mixed Value at `$member->memberIndex`, or null when the member is unset or not
     *               recognized.
     */
    public function getMemberValue(Schema $member): mixed;
}
