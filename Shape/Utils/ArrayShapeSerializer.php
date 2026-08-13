<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Utils;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;
use Smithy\Core\Serde\ShapeType;
use Smithy\Core\Shape\BigDecimal;
use Smithy\Core\Shape\BigInteger;
use Smithy\Core\Shape\Document\AbstractDocument;

/**
 * Schema-driven converter between generated shape objects and their V3-compatible associative
 * array form.
 *
 * `toArray()` walks `$shape::schema()->getMembersByIndex()` and writes the V3-equivalent array
 * (PascalCase wire keys, scalars passing through, structs and unions recursing, enums and
 * big-numerics unwrapping to their `->value`, lists and maps mapping element-wise).
 *
 * `fromArray()` takes a generated shape class name, resolves its schema through `::schema()`,
 * hydrates each present member into the matching PHP type, and named-args spreads the result into
 * `new $className(...)`.
 *
 * Wire keys are `Schema::$memberName` (PascalCase). Constructor argument names are the camelCase
 * property name (`lcfirst($memberName)`).
 */
final class ArrayShapeSerializer
{
    /**
     * Convert a generated shape into its V3-compatible associative array.
     *
     * @param SerializableStructInterface $shape Shape instance to convert.
     *
     * @return array<string, mixed> V3-shaped payload keyed by PascalCase wire member names.
     */
    public static function toArray(SerializableStructInterface $shape): array
    {
        $result = [];
        foreach ($shape::schema()->getMembersByIndex() as $memberSchema) {
            // Wire member name is PascalCase (e.g., `Status`). PHP property name is the camelCase
            // form. Resolve both so the array round-trips on the wire while reading from the
            // strict-typed property.
            $name = $memberSchema->memberName;
            $prop = \lcfirst($name);
            // `isset()` (not `!== null`) so the check is safe when a property is uninitialized.
            // Generated code may leave optional enum members uninitialized rather than null.
            if (!isset($shape->{$prop})) {
                continue;
            }
            $value = $shape->{$prop};

            $result[$name] = match ($memberSchema->type) {
                ShapeType::Structure->value => self::toArray($value),
                ShapeType::Union->value => [$value->tag() => $value->value()],
                ShapeType::Enum->value,
                ShapeType::IntEnum->value => $value->value,
                ShapeType::BigInteger->value,
                ShapeType::BigDecimal->value => $value->value,
                ShapeType::List_->value => self::unwrapList(
                    $value,
                    $memberSchema->getListMember()
                ),
                ShapeType::Map->value => self::unwrapMap(
                    $value,
                    $memberSchema->getMapValue()
                ),
                default => $value
            };
        }

        return $result;
    }

    /**
     * Build an instance of a generated shape from an associative array.
     *
     * @param class-string $className Generated structure or union class.
     * @param array<string, mixed> $data V3-shaped payload keyed by PascalCase wire member names.
     *
     * @return object Hydrated instance of `$className`.
     */
    public static function fromArray(string $className, array $data): object
    {
        /** @var Schema $schema */
        $schema = $className::schema();
        $args = [];
        foreach ($schema->getMembersByIndex() as $memberSchema) {
            $name = $memberSchema->memberName;
            if (!\array_key_exists($name, $data)) {
                // Missing key: skip the argument entirely. PHP fills the constructor's parameter
                // default for optional members. Required members raise TypeError at the call
                // boundary, which is the spec-correct missing-required behavior.
                continue;
            }
            // Constructor argument names are camelCase (`lcfirst($memberName)`). Wire keys stay
            // PascalCase.
            $args[\lcfirst($name)] = self::hydrateMember(
                $memberSchema,
                $data[$name]
            );
        }

        return new $className(...$args);
    }

    /**
     * Coerce one wire value into the PHP type its member expects.
     *
     * @param Schema $memberSchema Schema describing the member.
     * @param mixed $value Raw wire value.
     *
     * @return mixed Coerced value ready to pass to the constructor.
     * @throws \InvalidArgumentException When a timestamp string cannot be parsed.
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
     * @throws \InvalidArgumentException When `$value` cannot be parsed by `strtotime()`.
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
        throw new \InvalidArgumentException(
            "Invalid timestamp value '{$value}' for schema '{$label}'."
        );
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
     * Element-wise unwrap shared by list and map paths. Preserves keys.
     *
     * @param array<int|string, mixed> $items Elements to unwrap.
     * @param Schema $itemSchema Schema for the element type.
     *
     * @return array<int|string, mixed> Unwrapped elements, keys preserved.
     */
    private static function unwrapElements(array $items, Schema $itemSchema): array
    {
        $itemType = $itemSchema->type;
        // Hoist the type check out of the loop. For pass-through element types (scalars, list or
        // map without further nesting) we return the input unchanged. For struct, enum, or
        // bignum we map element-wise.
        switch ($itemType) {
            case ShapeType::Structure->value:
                $out = [];
                foreach ($items as $k => $v) {
                    // `@sparse` collections may carry nulls. Preserve them rather than
                    // dispatching the unwrap and erroring on the typed-param signature.
                    $out[$k] = $v === null ? null : self::toArray($v);
                }

                return $out;

            case ShapeType::Union->value:
                $out = [];
                foreach ($items as $k => $v) {
                    $out[$k] = $v === null ? null : [$v->tag() => $v->value()];
                }

                return $out;

            case ShapeType::Enum->value:
            case ShapeType::IntEnum->value:
                $out = [];
                foreach ($items as $k => $v) {
                    $out[$k] = $v === null ? null : $v->value;
                }

                return $out;

            case ShapeType::BigInteger->value:
            case ShapeType::BigDecimal->value:
                $out = [];
                foreach ($items as $k => $v) {
                    $out[$k] = $v === null ? null : $v->value;
                }

                return $out;

            default:
                return $items;
        }
    }
}
