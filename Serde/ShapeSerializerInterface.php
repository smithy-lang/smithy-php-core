<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;
use Smithy\Core\Shape\Document\DocumentInterface;

/**
 * Serializes Smithy data-model objects into a wire format.
 *
 * Implementations map each Smithy data type to its wire encoding (Smithy integers to JSON numbers,
 * structures to JSON objects, and so on).
 *
 * Generated shapes call the typed `serializeXxx()` method for each non-null member. The shape knows
 * the member type at codegen time, so there is no runtime type dispatch.
 *
 * Methods appear in the same order as their target case on {@see ShapeType}. Enum and IntEnum share
 * `serializeString` and `serializeInteger` respectively, the service-metadata cases (`Operation`,
 * `Service`, `Resource`) have no wire representation and no method here. The `beginStruct` /
 * `endStruct` pair sits alongside `serializeStruct` because it operates on the same struct-frame
 * concept.
 *
 * @see AbstractCodec for creating serializer instances.
 */
interface ShapeSerializerInterface
{
    /**
     * Serialize a boolean (ShapeType::Boolean) value.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param bool $value Value to encode.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeBoolean(Schema $schema, bool $value, int $idx): void;

    /**
     * Serialize an integer value (ShapeType::Byte, Short, Integer, Long).
     *
     * @param Schema $schema Member schema for the field being written.
     * @param int $value Value to encode.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeInteger(Schema $schema, int $value, int $idx): void;

    /**
     * Serialize a float/double (ShapeType::Float_, Double) value.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param float $value Value to encode.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeFloat(Schema $schema, float $value, int $idx): void;

    /**
     * Serialize an arbitrary-precision (ShapeType::BigInteger) integer.
     *
     * The wrapper holds a digit-only string, validated at construction. Wire formats that model
     * numbers as text (XML) emit `$value->value` directly. Formats that distinguish number tokens
     * from strings (JSON, CBOR) MUST emit the digits as an unquoted number token, never wrapped in
     * quotes.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param BigInteger $value Digit-string wrapper for the value to encode.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeBigInteger(Schema $schema, BigInteger $value, int $idx): void;

    /**
     * Serialize an arbitrary-precision (ShapeType::BigDecimal) decimal.
     *
     * The wrapper holds a numeric string, validated at construction. Emission rules match
     * `serializeBigInteger`.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param BigDecimal $value Numeric-string wrapper for the value to encode.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeBigDecimal(Schema $schema, BigDecimal $value, int $idx): void;

    /**
     * Serialize a string value (ShapeType::String_, Enum).
     *
     * @param Schema $schema Member schema for the field being written.
     * @param string $value Value to encode.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeString(Schema $schema, string $value, int $idx): void;

    /**
     * Serialize a blob (ShapeType::Blob) value.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param string $value Raw bytes to encode. The codec applies any wire-level encoding (e.g.
     *               base64) per the format.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeBlob(Schema $schema, string $value, int $idx): void;

    /**
     * Serialize a timestamp (ShapeType::Timestamp) value.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param float $value Epoch seconds. Integer values pass through as-is.
     * @param int $idx Positional index of the member in the parent frame.
     * @param TimestampFormat|null $format Codegen-resolved format hint. Null falls back to the
     *                             codec default.
     *
     * @return void
     */
    public function serializeTimestamp(
        Schema $schema,
        float $value, int $idx,
        ?TimestampFormat $format = null
    ): void;

    /**
     * Serialize a list (ShapeType::List_) shape.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param list<mixed> $list Ordered list of member values.
     * @param int $idx Positional index of the member in the parent frame.
     * @param ShapeType $memberType Type of list items (for type-dispatched serialization).
     *
     * @return void
     */
    public function serializeList(Schema $schema, array $list, int $idx, ShapeType $memberType): void;

    /**
     * Serialize a map (ShapeType::Map) shape.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param array<string, mixed> $map String-keyed map of member values.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeMap(Schema $schema, array $map, int $idx): void;

    /**
     * Serialize a structure (ShapeType::Structure) shape.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param SerializableStructInterface $struct Struct instance to encode.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeStruct(
        Schema $schema, SerializableStructInterface $struct,
        int $idx
    ): void;

    /**
     * Open a struct frame for direct per-member writes via `serializeXxx`.
     *
     * Pairs with `endStruct()`. The closing call writes the assembled frame into the parent at
     * `$idx`, or assigns it as the root if no parent frame is open. Prefer
     * `serializeStruct($schema, $struct, $idx)` when the caller has a `SerializableStructInterface`
     * on hand. This imperative pair is for callers that assemble struct contents from sources
     * outside a single `serializeMembers` walk, such as the HTTP binding layer combining body-bound
     * members across HTTP locations.
     *
     * @param Schema $schema Struct schema being opened.
     * @param int $idx Positional index at which the closing `endStruct()` will attach the assembled
     *            frame.
     *
     * @return void
     */
    public function beginStruct(Schema $schema, int $idx): void;

    /**
     * Close the struct frame opened by the matching `beginStruct` call.
     *
     * @return void
     */
    public function endStruct(): void;

    /**
     * Serialize a union (ShapeType::Union) shape.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param mixed $union Union instance to encode. The concrete runtime type is defined by the
     *                     generated union class.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeUnion(Schema $schema, mixed $union, int $idx): void;

    /**
     * Serialize a document (ShapeType::Document) value.
     *
     * Each concrete {@see DocumentInterface} implementation dispatches itself to the matching
     * `serialize*()` method on this interface via its own `serialize()`. Codecs that do not model
     * documents (XML, query) reject document members per the Smithy spec.
     *
     * A `null` value represents an absent member. The codec decides whether to emit a sentinel or
     * skip the field. An explicit document-level null arrives as.
     * {@see Smithy\Core\Shape\Document\NullDocument} instead.
     *
     * @param Schema $schema Member schema for the field being written.
     * @param DocumentInterface|null $value Document value, or null for an absent member.
     * @param int $idx Positional index of the member in the parent frame.
     *
     * @return void
     */
    public function serializeDocument(
        Schema $schema,
        ?DocumentInterface $value,
        int $idx,
    ): void;
}
