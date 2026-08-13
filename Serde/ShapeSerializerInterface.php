<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;
use Smithy\Core\Shape\Document\DocumentInterface;

/**
 * Serializes Smithy data-model objects into a wire format.
 *
 * Implementations map each Smithy data type to its wire encoding (Smithy
 * integers to JSON numbers, structures to JSON objects, and so on).
 *
 * Generated shapes call the typed `serializeXxx()` method for each non-null
 * member. The shape knows the member type at codegen time, so there is no
 * runtime type dispatch.
 *
 * @see AbstractCodec for creating serializer instances.
 */
interface ShapeSerializerInterface
{
    /**
     * Serialize a structure shape.
     */
    public function serializeStruct(Schema $schema, SerializableStructInterface $struct, int $idx): void;

    /**
     * Opens a struct frame for direct per-member writes via `serializeXxx`.
     *
     * Pairs with `endStruct()`. The closing call writes the assembled frame
     * into the parent at `$idx`, or assigns it as the root if no parent
     * frame is open. Prefer `serializeStruct($schema, $struct, $idx)` when
     * the caller has a `SerializableStructInterface` on hand. This
     * imperative pair is for callers that assemble struct contents from
     * sources outside a single `serializeMembers` walk, such as the HTTP
     * binding layer combining body-bound members across HTTP locations.
     */
    public function beginStruct(Schema $schema, int $idx): void;

    /**
     * Close the struct frame opened by the matching `beginStruct` call.
     */
    public function endStruct(): void;

    /**
     * Serialize a union shape.
     */
    public function serializeUnion(Schema $schema, mixed $union, int $idx): void;

    /**
     * Serialize a list shape.
     *
     * @param list<mixed> $list
     * @param ShapeType $memberType Type of list items (for type-dispatched serialization).
     */
    public function serializeList(Schema $schema, array $list, int $idx, ShapeType $memberType): void;

    /**
     * Serialize a map shape.
     *
     * @param array<string, mixed> $map
     */
    public function serializeMap(Schema $schema, array $map, int $idx): void;

    /**
     * Serialize a boolean value.
     */
    public function serializeBoolean(Schema $schema, bool $value, int $idx): void;

    /**
     * Serialize an integer value (byte, short, integer, long).
     */
    public function serializeInteger(Schema $schema, int $value, int $idx): void;

    /**
     * Serialize a float/double value.
     */
    public function serializeFloat(Schema $schema, float $value, int $idx): void;

    /**
     * Serialize an arbitrary-precision integer.
     *
     * The wrapper holds a digit-only string, validated at construction.
     * Wire formats that model numbers as text (XML) emit `$value->value`
     * directly. Formats that distinguish number tokens from strings
     * (JSON, CBOR) MUST emit the digits as an unquoted number token, never
     * wrapped in quotes.
     */
    public function serializeBigInteger(Schema $schema, BigInteger $value, int $idx): void;

    /**
     * Serialize an arbitrary-precision decimal.
     *
     * The wrapper holds a numeric string, validated at construction.
     * Emission rules match `serializeBigInteger`.
     */
    public function serializeBigDecimal(Schema $schema, BigDecimal $value, int $idx): void;

    /**
     * Serialize a string value (including enums).
     */
    public function serializeString(Schema $schema, string $value, int $idx): void;

    /**
     * Serialize a blob value.
     */
    public function serializeBlob(Schema $schema, string $value, int $idx): void;

    /**
     * Serialize a timestamp value.
     *
     * @param TimestampFormat|null $format Codegen-resolved format hint.
     */
    public function serializeTimestamp(Schema $schema, float $value, int $idx, ?TimestampFormat $format = null): void;

    /**
     * Serialize a document value.
     *
     * Each concrete {@see DocumentInterface} implementation dispatches
     * itself to the matching `serialize*()` method on this interface via
     * its own `serialize()`. Codecs that do not model documents (XML,
     * query) reject document members per the Smithy spec.
     *
     * A `null` value represents an absent member. The codec decides
     * whether to emit a sentinel or skip the field. An explicit
     * document-level null arrives as
     * {@see Smithy\Core\Shape\Document\NullDocument} instead.
     */
    public function serializeDocument(
        Schema $schema,
        ?DocumentInterface $value,
        int $idx,
    ): void;
}
