<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableShapeInterface;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Serde\ShapeType;
use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;

/**
 * Base class for {@see DocumentInterface} implementations.
 *
 * Each Smithy data-model type has a concrete subclass holding the value in the natural PHP runtime
 * type. Cross-type coercion goes through the `as*()` methods. The defaults here throw
 * {@see IncompatibleDocumentTypeException}; subclasses override the calls that legitimately succeed
 * for their data-model type, plus any widening numeric coercions.
 *
 * An attached {@see Schema} carries `@jsonName`, `@xmlName`, `@timestampFormat`, and other
 * serialization traits forward, so a document built from a modeled shape serializes through any
 * codec exactly as the shape itself would. Documents without a schema are untyped and carry only
 * the data-model type.
 *
 * @see ShapeSerializerInterface::serializeDocument()
 */
abstract class AbstractDocument implements DocumentInterface
{
    /**
     * @param Schema|null $schema Schema this document was built from. Null for untyped documents
     *                    (user-constructed data with no shape ID).
     */
    public function __construct(public readonly ?Schema $schema = null) {}

    /**
     * Return the Smithy data-model type carried by this document.
     *
     * @return ShapeType Data-model type of the concrete subclass.
     */
    abstract public function type(): ShapeType;

    /**
     * Return the attached schema, or null when the document is untyped.
     *
     * Exposes the constructor-promoted readonly property through the {@see DocumentInterface}
     * method contract so non-AbstractDocument implementations can honor it without subclassing.
     *
     * @return Schema|null Schema the document was built from, or null when untyped.
     */
    public function schema(): ?Schema
    {
        return $this->schema;
    }

    /**
     * Return the shape ID of the underlying schema, or null when no schema is attached.
     *
     * Used by type registries and codecs that emit `__type` discriminators.
     *
     * @return string|null Shape ID, or null when the document carries no schema or the schema has
     *                    no ID.
     */
    public function discriminator(): ?string
    {
        return $this->schema?->id;
    }

    /**
     * Drive a serializer with this document's contents.
     *
     * Each concrete subclass calls the matching `serializeXxx()` on the serializer, passing the
     * attached schema (if any) and the underlying value. Codec serializers that implement
     * {@see ShapeSerializerInterface::serializeDocument()} call this to delegate the write back
     * into themselves with a typed call.
     *
     * @param ShapeSerializerInterface $serializer Encoder to write into.
     * @param int $idx Member index in the parent struct, used by codecs that route members
     *            positionally (HTTP bindings).
     *
     * @return void
     */
    abstract public function serialize(ShapeSerializerInterface $serializer, int $idx): void;

    /**
     * @return bool Boolean value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a boolean-carrying
     *                                           subclass.
     */
    public function asBoolean(): bool
    {
        throw $this->incompatible('boolean');
    }

    /**
     * @return int Byte value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a byte-carrying
     *                                           subclass.
     */
    public function asByte(): int
    {
        throw $this->incompatible('byte');
    }

    /**
     * @return int Short value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a short-carrying
     *                                           subclass.
     */
    public function asShort(): int
    {
        throw $this->incompatible('short');
    }

    /**
     * @return int Integer value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by an integer-carrying
     *                                           subclass.
     */
    public function asInteger(): int
    {
        throw $this->incompatible('integer');
    }

    /**
     * @return int Long value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a long-carrying
     *                                           subclass.
     */
    public function asLong(): int
    {
        throw $this->incompatible('long');
    }

    /**
     * @return float Float value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a float-carrying
     *                                           subclass.
     */
    public function asFloat(): float
    {
        throw $this->incompatible('float');
    }

    /**
     * @return float Double value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a double-carrying
     *                                           subclass.
     */
    public function asDouble(): float
    {
        throw $this->incompatible('double');
    }

    /**
     * @return BigInteger Arbitrary-precision integer wrapper.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a bigInteger-carrying
     *                                           subclass.
     */
    public function asBigInteger(): BigInteger
    {
        throw $this->incompatible('bigInteger');
    }

    /**
     * @return BigDecimal Arbitrary-precision decimal wrapper.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a bigDecimal-carrying
     *                                           subclass.
     */
    public function asBigDecimal(): BigDecimal
    {
        throw $this->incompatible('bigDecimal');
    }

    /**
     * @return string String value.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a string-carrying
     *                                           subclass.
     */
    public function asString(): string
    {
        throw $this->incompatible('string');
    }

    /**
     * @return string Raw blob bytes.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a blob-carrying
     *                                           subclass.
     */
    public function asBlob(): string
    {
        throw $this->incompatible('blob');
    }

    /**
     * @return float Epoch seconds.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by a timestamp-carrying
     *                                           subclass.
     */
    public function asTimestamp(): float
    {
        throw $this->incompatible('timestamp');
    }

    /**
     * @return list<AbstractDocument> Ordered list of member documents.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by {@see ListDocument}.
     */
    public function asList(): array
    {
        throw $this->incompatible('list');
    }

    /**
     * @return array<string, AbstractDocument> Map from key to member document.
     * @throws IncompatibleDocumentTypeException Always, unless overridden by {@see MapDocument}.
     */
    public function asMap(): array
    {
        throw $this->incompatible('map');
    }

    /**
     * Look up a member by name on a map, struct, or union document.
     *
     * @param string $name Member name to look up.
     *
     * @return AbstractDocument|null Member document, or null when absent.
     * @throws IncompatibleDocumentTypeException When the document type has no named members
     *                                           (lists, scalars, blobs, timestamps).
     */
    public function getMember(string $name): ?AbstractDocument
    {
        throw $this->incompatible('member access');
    }

    /**
     * Build a {@see BooleanDocument} from a PHP bool.
     *
     * @param bool $value Boolean payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return BooleanDocument Document wrapping the value.
     */
    public static function ofBoolean(bool $value, ?Schema $schema = null): BooleanDocument
    {
        return new BooleanDocument($value, $schema);
    }

    /**
     * Build a {@see ByteDocument} from a PHP int.
     *
     * @param int $value Byte payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return ByteDocument Document wrapping the value.
     */
    public static function ofByte(int $value, ?Schema $schema = null): ByteDocument
    {
        return new ByteDocument($value, $schema);
    }

    /**
     * Build a {@see ShortDocument} from a PHP int.
     *
     * @param int $value Short payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return ShortDocument Document wrapping the value.
     */
    public static function ofShort(int $value, ?Schema $schema = null): ShortDocument
    {
        return new ShortDocument($value, $schema);
    }

    /**
     * Build an {@see IntegerDocument} from a PHP int.
     *
     * @param int $value Integer payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return IntegerDocument Document wrapping the value.
     */
    public static function ofInteger(int $value, ?Schema $schema = null): IntegerDocument
    {
        return new IntegerDocument($value, $schema);
    }

    /**
     * Build a {@see LongDocument} from a PHP int.
     *
     * @param int $value Long payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return LongDocument Document wrapping the value.
     */
    public static function ofLong(int $value, ?Schema $schema = null): LongDocument
    {
        return new LongDocument($value, $schema);
    }

    /**
     * Build a {@see FloatDocument} from a PHP float.
     *
     * @param float $value Float payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return FloatDocument Document wrapping the value.
     */
    public static function ofFloat(float $value, ?Schema $schema = null): FloatDocument
    {
        return new FloatDocument($value, $schema);
    }

    /**
     * Build a {@see DoubleDocument} from a PHP float.
     *
     * @param float $value Double payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return DoubleDocument Document wrapping the value.
     */
    public static function ofDouble(float $value, ?Schema $schema = null): DoubleDocument
    {
        return new DoubleDocument($value, $schema);
    }

    /**
     * Build a {@see BigIntegerDocument} from a {@see BigInteger}.
     *
     * @param BigInteger $value Arbitrary-precision integer.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return BigIntegerDocument Document wrapping the value.
     */
    public static function ofBigInteger(
        BigInteger $value,
        ?Schema $schema = null
    ): BigIntegerDocument
    {
        return new BigIntegerDocument($value, $schema);
    }

    /**
     * Build a {@see BigDecimalDocument} from a {@see BigDecimal}.
     *
     * @param BigDecimal $value Arbitrary-precision decimal.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return BigDecimalDocument Document wrapping the value.
     */
    public static function ofBigDecimal(
        BigDecimal $value,
        ?Schema $schema = null
    ): BigDecimalDocument
    {
        return new BigDecimalDocument($value, $schema);
    }

    /**
     * Build a {@see StringDocument} from a PHP string.
     *
     * @param string $value String payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return StringDocument Document wrapping the value.
     */
    public static function ofString(string $value, ?Schema $schema = null): StringDocument
    {
        return new StringDocument($value, $schema);
    }

    /**
     * Build a {@see BlobDocument} from raw bytes.
     *
     * @param string $bytes Blob payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return BlobDocument Document wrapping the bytes.
     */
    public static function ofBlob(string $bytes, ?Schema $schema = null): BlobDocument
    {
        return new BlobDocument($bytes, $schema);
    }

    /**
     * Build a {@see TimestampDocument} from an epoch-seconds value.
     *
     * @param float $epochSeconds Seconds since the Unix epoch. Fractional part carries
     *              sub-second precision.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return TimestampDocument Document wrapping the timestamp.
     */
    public static function ofTimestamp(
        float $epochSeconds,
        ?Schema $schema = null
    ): TimestampDocument
    {
        return new TimestampDocument($epochSeconds, $schema);
    }

    /**
     * Build a {@see ListDocument} from a list of documents.
     *
     * @param list<AbstractDocument> $items Ordered member documents.
     * @param Schema|null $schema Schema to attach, or null for an untyped list.
     *
     * @return ListDocument Document wrapping the items.
     */
    public static function ofList(array $items, ?Schema $schema = null): ListDocument
    {
        return new ListDocument($items, $schema);
    }

    /**
     * Build a {@see MapDocument} from a string-keyed map of documents.
     *
     * @param array<string, AbstractDocument> $entries Key-to-document entries.
     * @param Schema|null $schema Schema to attach, or null for an untyped map.
     *
     * @return MapDocument Document wrapping the entries.
     */
    public static function ofMap(array $entries, ?Schema $schema = null): MapDocument
    {
        return new MapDocument($entries, $schema);
    }

    /**
     * Build a {@see NullDocument}.
     *
     * @param Schema|null $schema Schema to attach, or null for an untyped null.
     *
     * @return NullDocument Document representing an absent value.
     */
    public static function ofNull(?Schema $schema = null): NullDocument
    {
        return new NullDocument($schema);
    }

    /**
     * Build a document from natural PHP data, recursing into arrays.
     *
     * Type mapping:
     *
     *   bool -> BooleanDocument
     *   int -> IntegerDocument
     *   float -> DoubleDocument
     *   string -> StringDocument
     *   null -> NullDocument
     *   list array -> ListDocument (children converted recursively)
     *   associative array -> MapDocument (values converted recursively)
     *   \stdClass -> MapDocument (object-like JSON object)
     *   AbstractDocument -> returned as-is
     *   BlobValue -> BlobDocument
     *   TimestampValue -> TimestampDocument
     *   BigInteger -> BigIntegerDocument
     *   BigDecimal -> BigDecimalDocument
     *
     * Wrap the payload in {@see BlobValue} to route a byte string to {@see BlobDocument}, or in
     * {@see TimestampValue} to route a numeric epoch to {@see TimestampDocument}.
     *
     * @param mixed $value Payload to wrap.
     * @param Schema|null $schema Schema to attach to the root document, or null when untyped.
     *              Recursively-built children are always untyped.
     *
     * @return AbstractDocument Document matching the payload's runtime type.
     * @throws \InvalidArgumentException When the payload's runtime type has no document mapping
     *                                   (resources, unrecognized objects).
     */
    public static function ofObject(mixed $value, ?Schema $schema = null): AbstractDocument
    {
        // gettype() gives one string per PHP runtime type. Object dispatches on class.
        switch (\gettype($value)) {
            case 'boolean':
                return new BooleanDocument($value, $schema);
            case 'integer':
                return new IntegerDocument($value, $schema);
            case 'double':
                // gettype() returns "double" for PHP floats.
                return new DoubleDocument($value, $schema);
            case 'string':
                return new StringDocument($value, $schema);
            case 'NULL':
                return new NullDocument($schema);
            case 'array':
                return \array_is_list($value)
                    ? self::buildList($value, $schema)
                    : self::buildMap($value, $schema);
            case 'object':
                return match (true) {
                    $value instanceof AbstractDocument => $value,
                    $value instanceof BigInteger => new BigIntegerDocument(
                        $value,
                        $schema
                    ),
                    $value instanceof BigDecimal => new BigDecimalDocument(
                        $value,
                        $schema
                    ),
                    $value instanceof BlobValue => new BlobDocument(
                        $value->bytes,
                        $schema
                    ),
                    $value instanceof TimestampValue => new TimestampDocument(
                        $value->epochSeconds,
                        $schema
                    ),
                    $value instanceof \stdClass => self::buildMap(
                        (array) $value,
                        $schema
                    ),
                    default => throw new \InvalidArgumentException(
                        'Cannot build a document from value of class '
                            . $value::class
                    )
                };
            default:
                // resource, resource (closed), or unknown type.
                throw new \InvalidArgumentException(
                    'Cannot build a document from value of type '
                        . \get_debug_type($value)
                );
        }
    }

    /**
     * Convert a PHP list to a {@see ListDocument}, recursing into each element.
     *
     * @param list<mixed> $items Elements to wrap.
     * @param Schema|null $schema Schema to attach to the resulting list document.
     *
     * @return ListDocument Document wrapping the converted elements.
     */
    private static function buildList(array $items, ?Schema $schema): ListDocument
    {
        $docs = [];
        foreach ($items as $v) {
            $docs[] = self::ofObject($v);
        }

        return new ListDocument($docs, $schema);
    }

    /**
     * Convert a PHP associative array to a {@see MapDocument}, recursing into each value.
     *
     * @param array<array-key, mixed> $entries Entries to wrap. Non-string keys are cast to string.
     * @param Schema|null $schema Schema to attach to the resulting map document.
     *
     * @return MapDocument Document wrapping the converted entries.
     */
    private static function buildMap(array $entries, ?Schema $schema): MapDocument
    {
        $docs = [];
        foreach ($entries as $k => $v) {
            $docs[(string) $k] = self::ofObject($v);
        }

        return new MapDocument($docs, $schema);
    }

    /**
     * Build the exception thrown by defaulting `as*()` methods.
     *
     * @param string $requested Name of the data-model type the caller asked for.
     *
     * @return IncompatibleDocumentTypeException Exception naming both the requested type and the
     *                                           actual document type.
     */
    protected function incompatible(string $requested): IncompatibleDocumentTypeException
    {
        return new IncompatibleDocumentTypeException(
            "Cannot coerce a {$this->type()->name} document to {$requested}"
        );
    }
}
