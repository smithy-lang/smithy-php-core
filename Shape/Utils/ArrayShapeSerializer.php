<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Utils;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;
use Smithy\Core\Shape\ShapeType;
use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;
use Smithy\Core\Shape\Document\AbstractDocument;
use Smithy\Core\Shape\EnumInterface;
use Smithy\Core\Shape\Exception\InvalidShapeValueException;
use Smithy\Core\Shape\UnionInterface;

/**
 * Convert generated shape objects to and from V3-compatible arrays.
 *
 * `toArray()` walks structure members or the active union variant. It
 * recursively unwraps structures, unions, collections, enums, and big
 * numbers.
 *
 * `fromArray()` resolves a generated structure or union schema, hydrates
 * each present member, and constructs the matching generated shape.
 *
 * Wire keys are modeled member names. Constructor arguments use camelCase
 * property names.
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
     * @param class-string<SerializableStructInterface|UnionInterface> $className Generated
     *        structure or union class.
     * @param array<string, mixed> $data V3-shaped payload keyed by modeled
     *        wire member names.
     *
     * @return SerializableStructInterface|UnionInterface Hydrated instance of `$className`.
     */
    public static function fromArray(string $className, array $data): object
    {
        if (!\is_a($className, SerializableStructInterface::class, true)
            && !\is_a($className, UnionInterface::class, true)
        ) {
            throw new InvalidShapeValueException(
                \sprintf(
                    'ArrayShapeSerializer::fromArray() expects a class implementing '
                    . '%s or %s; got %s.',
                    SerializableStructInterface::class,
                    UnionInterface::class,
                    $className
                )
            );
        }

        /** @var Schema $schema */
        $schema = $className::schema();
        if ($schema->type === ShapeType::Union->value) {
            return self::fromUnionArray($className, $schema, $data);
        }

        $args = [];
        foreach ($schema->getMembersByIndex() as $memberSchema) {
            $name = $memberSchema->memberName;
            if (!\array_key_exists($name, $data)) {
                // Skip missing keys so PHP applies optional parameter
                // defaults and rejects missing required parameters.
                continue;
            }
            $args[\lcfirst($name)] = self::hydrateMember(
                $memberSchema,
                $data[$name]
            );
        }

        return new $className(...$args);
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
                // Nested structures and unions recurse back through the serializer using the
                // target class named on the member schema.
                return self::fromArray($class, $value);

            case ShapeType::Enum->value:
            case ShapeType::IntEnum->value:
                $class = $memberSchema->targetClass;
                // Enum values may arrive as a wire scalar or as an instance of the generated enum
                // class. The generated constructor already normalizes both forms, so we pass
                // through either case unchanged.

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

            // V3-compat string coercion. V3's `Validator::checkCanString` accepts
            // string|int|float|stringable. Coerce here so the strict-typed `string $foo`
            // constructor parameter does not TypeError on non-string scalars from V3-shaped input.
            case ShapeType::String_->value:
                return match (\gettype($value)) {
                    'string' => $value,
                    'integer', 'double' => (string) $value,
                    'object' => \method_exists($value, '__toString')
                        ? (string) $value
                        : $value,
                    default => $value
                };

            // V3-compat integer-family coercion. V3's `check_numeric` accepts anything
            // `is_numeric` (numeric strings included). Coerce here so the strict-typed `int $foo`
            // parameter accepts numeric strings, matching the V3 input contract.
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

            // V3-compat blob coercion. Keep this to byte strings and stringable objects so plain
            // blob members continue to model raw bytes rather than arbitrary scalar conversion.
            case ShapeType::Blob->value:
                return match (\gettype($value)) {
                    'string' => $value,
                    'object' => \method_exists($value, '__toString')
                        ? (string) $value
                        : $value,
                    default => $value
                };

            // V3-compat document coercion. V3 callers pass raw arrays, stdClass, or scalars. Wrap
            // them into the matching `AbstractDocument` subclass so the strict-typed
            // `?AbstractDocument` parameter accepts them.
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
     * @param array<string, mixed> $map Raw map entries.
     * @param Schema|null $valueSchema Schema for the map's value type, or null when unknown.
     *
     * @return array<string, mixed> Hydrated map entries.
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
     * @param array<string, mixed> $map Entries to unwrap.
     * @param Schema|null $valueSchema Schema for the value type, or null when unknown.
     *
     * @return array<string, mixed> Unwrapped entries.
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
