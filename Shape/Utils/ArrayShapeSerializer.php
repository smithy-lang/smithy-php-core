<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Utils;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;
use Smithy\Core\Serde\TimestampFormat;
use Smithy\Core\Shape\ShapeType;
use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;
use Smithy\Core\Shape\Document\AbstractDocument;
use Smithy\Core\Shape\Document\DocumentInterface;
use Smithy\Core\Shape\Document\DocumentSettingsAwareInterface;
use Smithy\Core\Shape\Document\DocumentSettingsInterface;
use Smithy\Core\Shape\Document\IncompatibleDocumentTypeException;
use Smithy\Core\Shape\EnumInterface;
use Smithy\Core\Shape\Exception\InvalidShapeValueException;
use Smithy\Core\Shape\UnionInterface;

/**
 * Convert generated shape objects to and from V3-compatible arrays.
 *
 * `toArray()` walks structure members or the active union variant. It
 * recursively unwraps structures, unions, collections, documents, enums,
 * and big numbers.
 *
 * `fromArray()` resolves a generated structure or union schema, hydrates
 * each present member, and constructs the matching generated shape.
 *
 * Wire keys are modeled member names. Constructor argument names default to
 * `lcfirst($memberName)` and can be overridden by generated schema metadata.
 */
final class ArrayShapeSerializer
{
    /**
     * Convert a generated structure or union into its V3-compatible associative array.
     *
     * @param SerializableStructInterface|UnionInterface $shape Shape instance to convert.
     *
     * @return array<string, mixed> V3-shaped payload keyed by modeled wire member names.
     */
    public static function toArray(SerializableStructInterface|UnionInterface $shape): array
    {
        if ($shape instanceof UnionInterface) {
            return self::unwrapUnion($shape, $shape->getSchema());
        }

        $result = [];
        foreach ($shape->getSchema()->getMembersByIndex() as $memberSchema) {
            $name = $memberSchema->memberName;
            $value = $shape->getMemberValue($memberSchema);
            if ($value === null) {
                continue;
            }

            $result[$name] = self::unwrapValue($memberSchema, $value);
        }

        return $result;
    }

    /**
     * Build an instance of a generated shape from an associative array.
     *
     * @template TShape of SerializableStructInterface|UnionInterface
     *
     * @param class-string<TShape> $className Generated structure or union
     *        class.
     * @param array<string, mixed> $data V3-shaped payload keyed by modeled
     *        wire member names.
     *
     * @return TShape Hydrated instance of `$className`.
     * @throws \InvalidArgumentException When `$className` is a union variant class and the data
     *         names another variant.
     */
    public static function fromArray(string $className, array $data): object
    {
        $schema = self::classSchema($className);
        if ($schema->type === ShapeType::Union->value) {
            $union = self::fromUnionArray($className, $schema, $data);
            if (!$union instanceof $className) {
                // A variant class's create() builds whichever variant the data names.
                throw new \InvalidArgumentException(
                    "{$className} does not match the union variant in the data;"
                    . ' pass the union base class.'
                );
            }

            return $union;
        }

        $args = [];
        foreach ($schema->getMembersByIndex() as $memberSchema) {
            $name = $memberSchema->memberName;
            if ($name === null || !\array_key_exists($name, $data)) {
                continue;
            }

            $parameterName = $memberSchema->phpName
                ?? \lcfirst($name);
            $args[$parameterName] = self::hydrateMember(
                $memberSchema,
                $data[$name]
            );
        }

        return new $className(...$args);
    }

    /**
     * Build an instance of a generated shape from a document.
     *
     * Documents that carry codec settings ({@see DocumentSettingsAwareInterface}) read members by
     * `jsonName` only when the settings use it, and parse string timestamps with the member's
     * `timestampFormat` trait when the settings honor it, else the settings' default format.
     * Documents without settings read members by `jsonName` first, then by member name, and
     * timestamp members read through `asTimestamp()`. Blob members read through `asBlob()` and
     * aggregate members recurse through the member schema.
     *
     * @template TShape of SerializableStructInterface|UnionInterface
     *
     * @param class-string<TShape> $className Generated structure or union class.
     * @param DocumentInterface $document Document holding the shape's data.
     *
     * @return TShape Hydrated instance of `$className`.
     * @throws IncompatibleDocumentTypeException When a document cannot be coerced to the member
     *         type the schema declares.
     */
    public static function fromDocument(
        string $className,
        DocumentInterface $document
    ): object
    {
        $schema = self::classSchema($className);

        return self::fromArray(
            $className,
            self::documentToArray($schema, $document, null)
        );
    }

    /**
     * Read the static schema of a generated structure or union class.
     *
     * @param class-string<SerializableStructInterface|UnionInterface> $className
     *
     * @throws \InvalidArgumentException When the class has no static `schema()` method, or it
     *         returns something other than a Schema.
     */
    private static function classSchema(string $className): Schema
    {
        $schemaFactory = [$className, 'schema'];
        if (!\is_callable($schemaFactory)) {
            throw new \InvalidArgumentException(
                "{$className} must be a generated shape with a static schema() method."
            );
        }

        $schema = $schemaFactory();
        if (!$schema instanceof Schema) {
            throw new \InvalidArgumentException(
                "{$className}::schema() must return a Schema."
            );
        }

        return $schema;
    }

    /**
     * Convert a structure or union document into member-name keyed values.
     *
     * @param Schema $schema Structure or union schema.
     * @param DocumentInterface $document Document holding the shape's data.
     * @param DocumentSettingsInterface|null $inherited Settings of the enclosing document.
     *
     * @return array<string, mixed> Values keyed by modeled member name.
     */
    private static function documentToArray(
        Schema $schema,
        DocumentInterface $document,
        ?DocumentSettingsInterface $inherited
    ): array
    {
        $settings = self::settingsOf($document) ?? $inherited;
        $out = [];
        if ($schema->type === ShapeType::Union->value) {
            foreach ($document->asMap() as $wireName => $child) {
                if ($wireName === '__type') {
                    continue;
                }
                $member = self::findDocumentMember(
                    $schema,
                    (string) $wireName,
                    $settings
                );
                if ($member === null) {
                    return [(string) $wireName => $child === null
                        ? null
                        : self::unwrapDocument($child)];
                }

                return [$member->memberName => $child === null
                    ? null
                    : self::documentToValue($member, $child, $settings)];
            }

            return $out;
        }

        foreach ($schema->getMembersByIndex() as $member) {
            $name = $member->memberName;
            if ($name === null) {
                continue;
            }
            $jsonName = $settings?->usesJsonName() === false
                ? null
                : $member->getTrait(Schema::T_JSON_NAME);
            $child = (\is_string($jsonName) && $jsonName !== $name
                    ? $document->getMember($jsonName)
                    : null)
                ?? $document->getMember($name);
            if ($child !== null) {
                $out[$name] = self::documentToValue($member, $child, $settings);
            }
        }

        return $out;
    }

    /**
     * Read a numeric document as a float.
     *
     * @param DocumentInterface $document Integer-family or floating-point document.
     *
     * @return float Numeric value.
     */
    private static function numericToFloat(DocumentInterface $document): float
    {
        return match ($document->type()) {
            ShapeType::Byte,
            ShapeType::Short,
            ShapeType::Integer,
            ShapeType::Long => (float) $document->asLong(),
            default => $document->asDouble()
        };
    }

    /**
     * Return the codec settings a document carries.
     *
     * @param DocumentInterface $document Document to inspect.
     *
     * @return DocumentSettingsInterface|null Settings, or null for user-built documents.
     */
    private static function settingsOf(DocumentInterface $document): ?DocumentSettingsInterface
    {
        return $document instanceof DocumentSettingsAwareInterface
            ? $document->getDocumentSettings()
            : null;
    }

    /**
     * Read a timestamp member from a document.
     *
     * String documents parse with the member's `timestampFormat` trait when the settings honor
     * it, else the settings' default format. All other documents read through `asTimestamp()`.
     *
     * @param Schema $member Timestamp member or item schema.
     * @param DocumentInterface $document Document holding the value.
     * @param DocumentSettingsInterface|null $settings Settings in effect, or null.
     *
     * @return float Epoch seconds.
     * @throws IncompatibleDocumentTypeException When a string does not match the format.
     */
    private static function documentToTimestamp(
        Schema $member,
        DocumentInterface $document,
        ?DocumentSettingsInterface $settings
    ): float
    {
        if ($settings === null || $document->type() !== ShapeType::String_) {
            return $document->asTimestamp();
        }

        $format = ($settings->usesTimestampFormatTrait()
            ? TimestampFormat::fromTraitString(
                $member->getTrait(Schema::T_TIMESTAMP_FORMAT)
            )
            : null
        ) ?? $settings->getDefaultTimestampFormat();
        $value = $document->asString();
        if ($format === TimestampFormat::EpochSeconds && !\is_numeric($value)) {
            throw new IncompatibleDocumentTypeException(
                "Cannot parse '{$value}' as epoch-seconds"
            );
        }

        try {
            return $format->parse($value);
        } catch (\InvalidArgumentException $e) {
            throw new IncompatibleDocumentTypeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Find the member of a union schema that a wire name refers to.
     *
     * @param Schema $schema Union schema.
     * @param string $wireName `jsonName` or member name.
     * @param DocumentSettingsInterface|null $settings Settings of the document, or null.
     *
     * @return Schema|null Matching member, or null when none matches.
     */
    private static function findDocumentMember(
        Schema $schema,
        string $wireName,
        ?DocumentSettingsInterface $settings
    ): ?Schema
    {
        $useJsonName = $settings?->usesJsonName() !== false;
        foreach ($schema->getMembersByIndex() as $member) {
            if ($member->memberName === $wireName
                || ($useJsonName
                    && $member->getTrait(Schema::T_JSON_NAME) === $wireName)
            ) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Convert one document into the value its member schema describes.
     *
     * @param Schema $member Member or item schema.
     * @param DocumentInterface $document Document holding the value.
     * @param DocumentSettingsInterface|null $inherited Settings of the enclosing document.
     *
     * @return mixed Value ready for {@see hydrateMember()}.
     */
    private static function documentToValue(
        Schema $member,
        DocumentInterface $document,
        ?DocumentSettingsInterface $inherited
    ): mixed
    {
        $settings = self::settingsOf($document) ?? $inherited;
        switch ($member->type) {
            case ShapeType::Structure->value:
            case ShapeType::Union->value:
                return self::documentToArray(
                    $member->resolveTarget(),
                    $document,
                    $settings
                );
            case ShapeType::List_->value:
                $itemSchema = $member->getListMember();
                $out = [];
                foreach ($document->asList() as $item) {
                    $out[] = $item === null
                        ? null
                        : ($itemSchema === null
                            ? self::unwrapDocument($item)
                            : self::documentToValue($itemSchema, $item, $settings));
                }

                return $out;
            case ShapeType::Map->value:
                $valueSchema = $member->getMapValue();
                $out = [];
                foreach ($document->asMap() as $key => $item) {
                    $out[$key] = $item === null
                        ? null
                        : ($valueSchema === null
                            ? self::unwrapDocument($item)
                            : self::documentToValue($valueSchema, $item, $settings));
                }

                return $out;
            case ShapeType::Document->value:
                return $document;
            case ShapeType::Blob->value:
                return $document->asBlob();
            case ShapeType::Timestamp->value:
                return self::documentToTimestamp($member, $document, $settings);
            case ShapeType::Boolean->value:
                return $document->asBoolean();
            case ShapeType::Byte->value:
            case ShapeType::Short->value:
            case ShapeType::Integer->value:
            case ShapeType::Long->value:
            case ShapeType::IntEnum->value:
                return $document->asLong();
            case ShapeType::Float_->value:
            case ShapeType::Double->value:
                return self::numericToFloat($document);
            case ShapeType::BigInteger->value:
                return $document->asBigInteger();
            case ShapeType::BigDecimal->value:
                return $document->asBigDecimal();
            case ShapeType::String_->value:
            case ShapeType::Enum->value:
                return $document->asString();
            default:
                return self::unwrapDocument($document);
        }
    }

    /**
     * Build a generated union instance from an associative array.
     *
     * @param class-string $className Generated union base class.
     * @param Schema $schema Root union schema.
     * @param array<string, mixed> $data V3 union payload with one active
     *        variant key.
     *
     * @return object Hydrated union instance.
     */
    private static function fromUnionArray(
        string $className,
        Schema $schema,
        array $data
    ): object
    {
        $tag = \array_key_first($data);
        if ($tag === null) {
            return $className::create('__unknown', null);
        }

        $memberSchema = $schema->getMember((string) $tag);
        $value = $data[$tag];
        if ($memberSchema === null) {
            return $className::create((string) $tag, $value);
        }

        return $className::create(
            (string) $tag,
            self::hydrateMember($memberSchema, $value)
        );
    }

    /**
     * Coerce one wire value into the PHP type its member expects.
     *
     * @param Schema $memberSchema Schema describing the member.
     * @param mixed $value Raw wire value.
     *
     * @return mixed Coerced value ready to pass to the constructor.
     * @throws InvalidShapeValueException When a timestamp string cannot be parsed.
     */
    private static function hydrateMember(Schema $memberSchema, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        switch ($memberSchema->type) {
            case ShapeType::Structure->value:
            case ShapeType::Union->value:
                $class = $memberSchema->targetClass;
                if ($class === null || !\is_array($value)) {
                    return $value;
                }
                if (!\is_a($class, SerializableStructInterface::class, true)
                    && !\is_a($class, UnionInterface::class, true)
                ) {
                    throw new \InvalidArgumentException(
                        "{$class} must be a generated shape with a static schema() method."
                    );
                }
                // Nested structures and unions recurse through the serializer
                // using the target class named on the member schema.
                return self::fromArray($class, $value);

            case ShapeType::Enum->value:
            case ShapeType::IntEnum->value:
                $class = $memberSchema->targetClass;
                // Enum values may arrive as a wire scalar or as an instance
                // of the generated enum class. The generated constructor
                // already normalizes both forms, so pass either case through.

                return $class !== null && !($value instanceof $class)
                    ? $class::fromValue($value)
                    : $value;

            case ShapeType::BigInteger->value:
                return $value instanceof BigInteger
                    ? $value
                    : new BigInteger((string) $value);

            case ShapeType::BigDecimal->value:
                return $value instanceof BigDecimal
                    ? $value
                    : new BigDecimal((string) $value);

            case ShapeType::Timestamp->value:
                if ($value instanceof \DateTimeInterface) {
                    return $value->getTimestamp();
                }

                if (\is_string($value)) {
                    return self::parseTimestampString($memberSchema, $value);
                }

                return $value;

            case ShapeType::Float_->value:
            case ShapeType::Double->value:
                if (\is_string($value)) {
                    return match ($value) {
                        'NaN' => \NAN,
                        'Infinity' => \INF,
                        '-Infinity' => -\INF,
                        default => (float) $value
                    };
                }

                return $value;

            // V3's `Validator::checkCanString` accepts string, int, float,
            // and stringable values. Coerce here so strict string parameters
            // accept non-string scalars from V3-shaped input.
            case ShapeType::String_->value:
                return match (\gettype($value)) {
                    'string' => $value,
                    'integer', 'double' => (string) $value,
                    'object' => \method_exists($value, '__toString')
                        ? (string) $value
                        : $value,
                    default => $value
                };

            // V3's `check_numeric` accepts any is_numeric value. Coerce here
            // so strict integer parameters accept numeric strings.
            case ShapeType::Integer->value:
            case ShapeType::Long->value:
            case ShapeType::Short->value:
            case ShapeType::Byte->value:
                return match (\gettype($value)) {
                    'integer' => $value,
                    'string', 'double' => \is_numeric($value)
                        ? (int) $value
                        : $value,
                    default => $value
                };

            // Keep blob coercion to byte strings and stringable objects so
            // blob members continue to model raw bytes.
            case ShapeType::Blob->value:
                return match (\gettype($value)) {
                    'string' => $value,
                    'object' => \method_exists($value, '__toString')
                        ? (string) $value
                        : $value,
                    default => $value
                };

            // V3 callers pass raw arrays, stdClass, or scalars. Wrap them
            // into the matching document subclass.
            case ShapeType::Document->value:
                return $value instanceof AbstractDocument
                    ? $value
                    : AbstractDocument::fromValue($value);

            case ShapeType::List_->value:
                return self::hydrateList(
                    $value,
                    $memberSchema->getListMember()
                );

            case ShapeType::Map->value:
                return self::hydrateMap($value, $memberSchema->getMapValue());

            default:
                return $value;
        }
    }

    /**
     * Parse a timestamp string for a schema member.
     *
     * @param Schema $memberSchema Schema describing the timestamp-bearing member or item.
     * @param string $value Raw timestamp string.
     *
     * @return int Parsed Unix timestamp.
     * @throws InvalidShapeValueException When `$value` cannot be parsed by `strtotime()`.
     */
    private static function parseTimestampString(
        Schema $memberSchema,
        string $value
    ): int
    {
        $parsed = \strtotime($value);
        if ($parsed !== false) {
            return $parsed;
        }

        $label = $memberSchema->memberName ?? $memberSchema->id;
        throw new InvalidShapeValueException(
            "Invalid timestamp value '{$value}' for schema '{$label}'."
        );
    }

    /**
     * Normalize one value into the array form described by its schema.
     *
     * @param Schema $schema Schema describing the runtime value.
     * @param mixed $value Runtime value to unwrap.
     *
     * @return mixed V3-compatible array payload fragment.
     */
    private static function unwrapValue(Schema $schema, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $resolved = $schema->resolveTarget();

        return match ($schema->type) {
            ShapeType::Structure->value => self::toArray($value),
            ShapeType::Union->value => self::unwrapUnion($value, $resolved),
            ShapeType::Enum->value,
            ShapeType::IntEnum->value => $value instanceof EnumInterface
                ? $value->getValue()
                : $value,
            ShapeType::BigInteger->value,
            ShapeType::BigDecimal->value => $value->value,
            ShapeType::Document->value => self::unwrapDocumentValue($value),
            ShapeType::List_->value => self::unwrapList(
                $value,
                $resolved->getListMember()
            ),
            ShapeType::Map->value => self::unwrapMap(
                $value,
                $resolved->getMapValue()
            ),
            default => $value
        };
    }

    /**
     * Recursively unwrap document objects in a document value.
     *
     * Raw scalar and array values are accepted for compatibility with
     * hand-written shape implementations.
     *
     * @param mixed $value Document object or native PHP value.
     *
     * @return mixed Native PHP document value.
     */
    private static function unwrapDocumentValue(mixed $value): mixed
    {
        if ($value instanceof DocumentInterface) {
            return self::unwrapDocument($value);
        }

        if (!\is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $entry) {
            $out[$key] = self::unwrapDocumentValue($entry);
        }

        return $out;
    }

    /**
     * Recursively normalize a document into native PHP values.
     *
     * @param DocumentInterface $document Document value to unwrap.
     *
     * @return mixed Native PHP document value.
     * @throws InvalidShapeValueException When the document carries a non-data-model type.
     */
    private static function unwrapDocument(DocumentInterface $document): mixed
    {
        return match ($document->type()) {
            ShapeType::Boolean => $document->asBoolean(),
            ShapeType::Byte => $document->asByte(),
            ShapeType::Short => $document->asShort(),
            ShapeType::Integer,
            ShapeType::IntEnum => $document->asInteger(),
            ShapeType::Long => $document->asLong(),
            ShapeType::Float_ => $document->asFloat(),
            ShapeType::Double => $document->asDouble(),
            ShapeType::BigInteger => $document->asBigInteger()->value,
            ShapeType::BigDecimal => $document->asBigDecimal()->value,
            ShapeType::String_,
            ShapeType::Enum => $document->asString(),
            ShapeType::Blob => $document->asBlob(),
            ShapeType::Timestamp => $document->asTimestamp(),
            ShapeType::List_ => self::unwrapDocumentValue(
                $document->asList()
            ),
            ShapeType::Structure => self::unwrapDocumentValue(
                \array_filter(
                    $document->asMap(),
                    static fn (mixed $value): bool => $value !== null
                )
            ),
            ShapeType::Map,
            ShapeType::Union => self::unwrapDocumentValue(
                $document->asMap()
            ),
            default => throw new InvalidShapeValueException(
                'Cannot unwrap a document carrying '
                    . $document->type()->name
                    . '.'
            )
        };
    }

    /**
     * Normalize a generated union into its single-entry associative array.
     *
     * @param UnionInterface $union Hydrated union variant.
     * @param Schema $schema Root union schema.
     *
     * @return array<string, mixed> Single-entry V3-shaped union payload.
     */
    private static function unwrapUnion(
        UnionInterface $union,
        Schema $schema
    ): array
    {
        $tag = $union->tag();
        $value = $union->value();
        $memberSchema = $schema->getMember($tag);

        return [
            $tag => $memberSchema === null
                ? $value
                : self::unwrapValue($memberSchema, $value)
        ];
    }

    /**
     * @param array<int|string, mixed> $list Raw list items.
     * @param Schema|null $itemSchema Schema for the list's item type, or null when unknown.
     *
     * @return array<int|string, mixed> Hydrated list items.
     */
    private static function hydrateList(array $list, ?Schema $itemSchema): array
    {
        if ($itemSchema === null) {
            return $list;
        }

        $out = [];
        foreach ($list as $item) {
            $out[] = self::hydrateMember($itemSchema, $item);
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $map Raw map entries.
     * @param Schema|null $valueSchema Schema for the map's value type, or null when unknown.
     *
     * @return array<array-key, mixed> Hydrated map entries.
     */
    private static function hydrateMap(array $map, ?Schema $valueSchema): array
    {
        if ($valueSchema === null) {
            return $map;
        }

        $out = [];
        foreach ($map as $k => $v) {
            $out[$k] = self::hydrateMember($valueSchema, $v);
        }

        return $out;
    }

    /**
     * @param array<int|string, mixed> $list Elements to unwrap.
     * @param Schema|null $itemSchema Schema for the item type, or null when unknown.
     *
     * @return array<int|string, mixed> Unwrapped elements.
     */
    private static function unwrapList(array $list, ?Schema $itemSchema): array
    {
        if ($itemSchema === null) {
            return $list;
        }

        return self::unwrapElements($list, $itemSchema);
    }

    /**
     * @param array<array-key, mixed> $map Entries to unwrap.
     * @param Schema|null $valueSchema Schema for the value type, or null when unknown.
     *
     * @return array<array-key, mixed> Unwrapped entries.
     */
    private static function unwrapMap(array $map, ?Schema $valueSchema): array
    {
        if ($valueSchema === null) {
            return $map;
        }

        return self::unwrapElements($map, $valueSchema);
    }

    /**
     * Unwrap list or map values while preserving keys and sparse nulls.
     *
     * @param array<int|string, mixed> $items Elements to unwrap.
     * @param Schema $itemSchema Schema for the element type.
     *
     * @return array<int|string, mixed> Unwrapped elements, keys preserved.
     */
    private static function unwrapElements(array $items, Schema $itemSchema): array
    {
        $out = [];
        foreach ($items as $k => $v) {
            $out[$k] = $v === null ? null : self::unwrapValue($itemSchema, $v);
        }

        return $out;
    }
}
