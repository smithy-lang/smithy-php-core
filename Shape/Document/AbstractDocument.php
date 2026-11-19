<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\ShapeType;
use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;
use Smithy\Core\Shape\EnumInterface;
use Smithy\Core\Shape\Exception\InvalidShapeValueException;
use Smithy\Core\Shape\Utils\ArrayShapeSerializer;
use Smithy\Core\Shape\UnionInterface;

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
     * Build a modeled shape from this document using the schema of the generated class.
     *
     * @template T of SerializableStructInterface|UnionInterface
     *
     * @param class-string<T> $class Generated structure or union class.
     *
     * @return T Hydrated shape instance.
     * @throws IncompatibleDocumentTypeException When a document cannot be coerced to the member
     *         type the schema declares.
     */
    public function asShape(string $class): object
    {
        return ArrayShapeSerializer::fromDocument($class, $this);
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
     * @throws IncompatibleDocumentTypeException Unless overridden by a boolean-carrying subclass.
     */
    public function asBoolean(): bool
    {
        throw $this->incompatible('boolean');
    }

    /**
     * @return int Byte value.
     * @throws IncompatibleDocumentTypeException Unless overridden by a byte-carrying subclass.
     */
    public function asByte(): int
    {
        throw $this->incompatible('byte');
    }

    /**
     * @return int Short value.
     * @throws IncompatibleDocumentTypeException Unless overridden by a short-carrying subclass.
     */
    public function asShort(): int
    {
        throw $this->incompatible('short');
    }

    /**
     * @return int Integer value.
     * @throws IncompatibleDocumentTypeException Unless overridden by an integer-carrying subclass.
     */
    public function asInteger(): int
    {
        throw $this->incompatible('integer');
    }

    /**
     * @return int Long value.
     * @throws IncompatibleDocumentTypeException Unless overridden by a long-carrying subclass.
     */
    public function asLong(): int
    {
        throw $this->incompatible('long');
    }

    /**
     * @return float Float value.
     * @throws IncompatibleDocumentTypeException Unless overridden by a float-carrying subclass.
     */
    public function asFloat(): float
    {
        throw $this->incompatible('float');
    }

    /**
     * @return float Double value.
     * @throws IncompatibleDocumentTypeException Unless overridden by a double-carrying subclass.
     */
    public function asDouble(): float
    {
        throw $this->incompatible('double');
    }

    /**
     * @return BigInteger Arbitrary-precision integer wrapper.
     * @throws IncompatibleDocumentTypeException Unless overridden by a bigInteger-carrying subclass.
     */
    public function asBigInteger(): BigInteger
    {
        throw $this->incompatible('bigInteger');
    }

    /**
     * @return BigDecimal Arbitrary-precision decimal wrapper.
     * @throws IncompatibleDocumentTypeException Unless overridden by a bigDecimal-carrying subclass.
     */
    public function asBigDecimal(): BigDecimal
    {
        throw $this->incompatible('bigDecimal');
    }

    /**
     * @return string String value.
     * @throws IncompatibleDocumentTypeException Unless overridden by a string-carrying subclass.
     */
    public function asString(): string
    {
        throw $this->incompatible('string');
    }

    /**
     * @return string Raw blob bytes.
     * @throws IncompatibleDocumentTypeException Unless overridden by a blob-carrying subclass.
     */
    public function asBlob(): string
    {
        throw $this->incompatible('blob');
    }

    /**
     * @return float Epoch seconds.
     * @throws IncompatibleDocumentTypeException Unless overridden by a timestamp-carrying subclass.
     */
    public function asTimestamp(): float
    {
        throw $this->incompatible('timestamp');
    }

    /**
     * @return list<AbstractDocument|null> Ordered list of member documents.
     * @throws IncompatibleDocumentTypeException Unless overridden by {@see ListDocument}.
     */
    public function asList(): array
    {
        throw $this->incompatible('list');
    }

    /**
     * @return array<string, AbstractDocument|null> Map from key to member document.
     * @throws IncompatibleDocumentTypeException Unless overridden by {@see MapDocument}.
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
     * @return AbstractDocument|null Member document, or null when absent or explicitly null.
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
    public static function fromBoolean(
        bool $value,
        ?Schema $schema = null
    ): BooleanDocument
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
    public static function fromByte(int $value, ?Schema $schema = null): ByteDocument
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
    public static function fromShort(int $value, ?Schema $schema = null): ShortDocument
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
    public static function fromInteger(
        int $value,
        ?Schema $schema = null
    ): IntegerDocument
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
    public static function fromLong(int $value, ?Schema $schema = null): LongDocument
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
    public static function fromFloat(
        float $value,
        ?Schema $schema = null
    ): FloatDocument
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
    public static function fromDouble(
        float $value,
        ?Schema $schema = null
    ): DoubleDocument
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
    public static function fromBigInteger(
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
    public static function fromBigDecimal(
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
    public static function fromString(
        string $value,
        ?Schema $schema = null
    ): StringDocument
    {
        return new StringDocument($value, $schema);
    }

    /**
     * Build a {@see BlobDocument} from raw bytes or a blob wrapper.
     *
     * @param string|BlobValue $bytes Blob payload or already-boxed blob marker.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return BlobDocument Document wrapping the bytes.
     */
    public static function fromBlob(
        string|BlobValue $bytes,
        ?Schema $schema = null
    ): BlobDocument
    {
        return new BlobDocument(
            $bytes instanceof BlobValue ? $bytes->bytes : $bytes,
            $schema
        );
    }

    /**
     * Build a {@see TimestampDocument} from an epoch-seconds value or timestamp wrapper.
     *
     * @param float|TimestampValue $epochSeconds Seconds since the Unix epoch, or a boxed timestamp
     *              marker. Fractional part carries sub-second precision.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @return TimestampDocument Document wrapping the timestamp.
     */
    public static function fromTimestamp(
        float|TimestampValue $epochSeconds,
        ?Schema $schema = null
    ): TimestampDocument
    {
        return new TimestampDocument(
            $epochSeconds instanceof TimestampValue
                ? $epochSeconds->epochSeconds
                : $epochSeconds,
            $schema
        );
    }

    /**
     * Build a {@see ListDocument} from a list of documents.
     *
     * @param list<AbstractDocument|null> $items Ordered member documents.
     * @param Schema|null $schema Schema to attach, or null for an untyped list.
     *
     * @return ListDocument Document wrapping the items.
     */
    public static function fromList(
        array $items,
        ?Schema $schema = null
    ): ListDocument
    {
        return new ListDocument($items, $schema);
    }

    /**
     * Build a {@see MapDocument} from a string-keyed map of documents.
     *
     * @param array<string, AbstractDocument|null> $entries Key-to-document entries.
     * @param Schema|null $schema Schema to attach, or null for an untyped map.
     *
     * @return MapDocument Document wrapping the entries.
     */
    public static function fromMap(
        array $entries,
        ?Schema $schema = null
    ): MapDocument
    {
        return new MapDocument($entries, $schema);
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
     *   null -> null
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
     * {@see TimestampValue} to route a numeric epoch to {@see TimestampDocument}. Call
     * {@see fromBlob()} or {@see fromTimestamp()} directly when you already know the target type.
     *
     * @param mixed $value Payload to wrap.
     * @param Schema|null $schema Schema to attach to the root document, or null when untyped.
     *              Recursively-built children are always untyped.
     *
     * @return AbstractDocument|null Document matching the payload's runtime type.
     * @throws InvalidShapeValueException When the payload's runtime type has no document mapping
     *                                    (resources, unrecognized objects).
     */
    public static function fromValue(
        mixed $value,
        ?Schema $schema = null
    ): ?AbstractDocument
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
                return null;
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
                    default => throw new InvalidShapeValueException(
                        'Cannot build a document from value of class '
                            . $value::class
                    )
                };
            default:
                // resource, resource (closed), or unknown type.
                throw new InvalidShapeValueException(
                    'Cannot build a document from value of type '
                        . \get_debug_type($value)
                );
        }
    }

    /**
     * Build a document using a modeled schema to preserve Smithy types.
     *
     * @param Schema $schema Schema describing the value.
     * @param mixed $value Runtime value.
     *
     * @return AbstractDocument|null Schema-backed document.
     */
    public static function fromSchemaValue(
        Schema $schema,
        mixed $value
    ): ?AbstractDocument
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StructDocument
            && $schema->type === ShapeType::Structure->value
        ) {
            return $value->withSerializationSchema($schema);
        }

        if ($value instanceof UnionDocument
            && $schema->type === ShapeType::Union->value
        ) {
            return $value->withSerializationSchema($schema);
        }

        if ($value instanceof AbstractDocument) {
            return $value;
        }

        switch ($schema->type) {
            case ShapeType::Boolean->value:
                return new BooleanDocument((bool) $value, $schema);
            case ShapeType::Byte->value:
                return new ByteDocument((int) $value, $schema);
            case ShapeType::Short->value:
                return new ShortDocument((int) $value, $schema);
            case ShapeType::Integer->value:
                return new IntegerDocument((int) $value, $schema);
            case ShapeType::Long->value:
                return new LongDocument((int) $value, $schema);
            case ShapeType::Float_->value:
                return new FloatDocument((float) $value, $schema);
            case ShapeType::Double->value:
                return new DoubleDocument((float) $value, $schema);
            case ShapeType::BigInteger->value:
                return new BigIntegerDocument(
                    $value instanceof BigInteger
                        ? $value
                        : new BigInteger((string) $value),
                    $schema
                );
            case ShapeType::BigDecimal->value:
                return new BigDecimalDocument(
                    $value instanceof BigDecimal
                        ? $value
                        : new BigDecimal((string) $value),
                    $schema
                );
            case ShapeType::String_->value:
            case ShapeType::Enum->value:
                return new StringDocument(
                    $value instanceof EnumInterface
                        ? (string) $value->getValue()
                        : (string) $value,
                    $schema
                );
            case ShapeType::Blob->value:
                return new BlobDocument((string) $value, $schema);
            case ShapeType::Timestamp->value:
                return new TimestampDocument((float) $value, $schema);
            case ShapeType::IntEnum->value:
                return new IntegerDocument(
                    $value instanceof EnumInterface
                        ? (int) $value->getValue()
                        : (int) $value,
                    $schema
                );
            case ShapeType::Structure->value:
                if ($value instanceof SerializableStructInterface) {
                    return StructDocument::fromStruct($value, $schema);
                }
                if (\is_array($value)) {
                    return StructDocument::fromMembers($schema, $value);
                }
                break;
            case ShapeType::Union->value:
                if ($value instanceof UnionInterface) {
                    return UnionDocument::fromUnion($value, $schema);
                }
                break;
            case ShapeType::List_->value:
                if (\is_array($value)) {
                    $items = [];
                    $member = $schema->getListMember();
                    foreach ($value as $item) {
                        $items[] = $member === null
                            ? self::fromValue($item)
                            : self::fromSchemaValue($member, $item);
                    }

                    return new ListDocument($items, $schema);
                }
                break;
            case ShapeType::Map->value:
                if (\is_array($value)) {
                    $entries = [];
                    $mapValue = $schema->getMapValue();
                    foreach ($value as $key => $entry) {
                        $entries[(string) $key] = $mapValue === null
                            ? self::fromValue($entry)
                            : self::fromSchemaValue($mapValue, $entry);
                    }

                    return new MapDocument($entries, $schema);
                }
                break;
            case ShapeType::Document->value:
                return self::fromValue($value, $schema);
        }

        throw new InvalidShapeValueException(
            'Cannot build a '
                . ShapeType::from($schema->type)->name
                . ' document from '
                . \get_debug_type($value)
        );
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
            $docs[] = self::fromValue($v);
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
            $docs[(string) $k] = self::fromValue($v);
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
