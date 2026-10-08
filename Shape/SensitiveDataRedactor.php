<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;

/**
 * Builds safe debug representations of modeled values.
 *
 * @internal
 */
final class SensitiveDataRedactor
{
    /**
     * Marker used in place of sensitive values.
     */
    public const REDACTED = '***REDACTED***';

    private const MAX_DEPTH = 64;
    private const MAX_DEPTH_REACHED = '<maximum debug depth reached>';

    public static function debugInfo(
        SerializableStructInterface|UnionInterface $value
    ): array
    {
        return $value instanceof SerializableStructInterface
            ? self::redactStructure($value, $value->getSchema(), 0)
            : self::redactUnion($value, $value->getSchema(), 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function redactStructure(
        SerializableStructInterface $value,
        Schema $schema,
        int $depth
    ): array
    {
        $result = [];
        $redactAll = self::isSensitive($schema);
        foreach ($schema->getMembers() as $memberName => $memberSchema) {
            $memberValue = $value->getMemberValue($memberSchema);
            if ($memberValue === null) {
                continue;
            }

            $result[$memberName] = $redactAll
                ? self::REDACTED
                : self::redactValue(
                    $memberValue,
                    $memberSchema,
                    $depth + 1
                );
        }

        return $result;
    }

    /**
     * @return array{tag: string, value: mixed}
     */
    private static function redactUnion(
        UnionInterface $value,
        Schema $schema,
        int $depth
    ): array
    {
        $memberIndex = $value->getMemberIndex();
        $memberSchema = $memberIndex < 0
            ? null
            : $schema->getMemberByIndex($memberIndex);

        return [
            'tag' => $value->tag(),
            'value' => self::isSensitive($schema) || $memberSchema === null
                ? self::REDACTED
                : self::redactValue(
                    $value->value(),
                    $memberSchema,
                    $depth + 1
                )
        ];
    }

    private static function redactValue(
        mixed $value,
        Schema $schema,
        int $depth
    ): mixed
    {
        if (self::isSensitive($schema)) {
            return self::REDACTED;
        }
        if ($depth >= self::MAX_DEPTH) {
            return self::MAX_DEPTH_REACHED;
        }

        return match ($schema->type) {
            ShapeType::Structure->value => self::redactStructureValue(
                $value,
                $depth
            ),
            ShapeType::Union->value => self::redactUnionValue(
                $value,
                $depth
            ),
            ShapeType::List_->value => self::redactList(
                $value,
                $schema,
                $depth
            ),
            ShapeType::Map->value => self::redactMap(
                $value,
                $schema,
                $depth
            ),
            default => $value
        };
    }

    private static function redactStructureValue(
        mixed $value,
        int $depth
    ): mixed
    {
        return $value instanceof SerializableStructInterface
            ? self::redactStructure($value, $value->getSchema(), $depth)
            : self::safeUnknownValue($value);
    }

    private static function redactUnionValue(
        mixed $value,
        int $depth
    ): mixed
    {
        return $value instanceof UnionInterface
            ? self::redactUnion($value, $value->getSchema(), $depth)
            : self::safeUnknownValue($value);
    }

    private static function redactList(
        mixed $value,
        Schema $schema,
        int $depth
    ): mixed
    {
        if (!\is_array($value)) {
            return self::safeUnknownValue($value);
        }

        $memberSchema = self::listMember($schema);
        if ($memberSchema === null) {
            return self::REDACTED;
        }

        $result = [];
        foreach ($value as $index => $memberValue) {
            $result[$index] = $memberValue === null
                ? null
                : self::redactValue(
                    $memberValue,
                    $memberSchema,
                    $depth + 1
                );
        }

        return $result;
    }

    private static function redactMap(
        mixed $value,
        Schema $schema,
        int $depth
    ): mixed
    {
        if (!\is_array($value)) {
            return self::safeUnknownValue($value);
        }

        $keySchema = self::mapKey($schema);
        $valueSchema = self::mapValue($schema);
        if ($keySchema === null
            || $valueSchema === null
            || self::isSensitive($keySchema)
        ) {
            return self::REDACTED;
        }

        $result = [];
        foreach ($value as $key => $mapValue) {
            $result[$key] = $mapValue === null
                ? null
                : self::redactValue(
                    $mapValue,
                    $valueSchema,
                    $depth + 1
                );
        }

        return $result;
    }

    private static function listMember(Schema $schema): ?Schema
    {
        $member = $schema->getListMember();
        if ($member !== null) {
            return $member;
        }

        return $schema->resolveTarget()->getListMember();
    }

    private static function mapKey(Schema $schema): ?Schema
    {
        $key = $schema->getMapKey();
        if ($key !== null) {
            return $key;
        }

        return $schema->resolveTarget()->getMapKey();
    }

    private static function mapValue(Schema $schema): ?Schema
    {
        $value = $schema->getMapValue();
        if ($value !== null) {
            return $value;
        }

        return $schema->resolveTarget()->getMapValue();
    }

    private static function isSensitive(Schema $schema): bool
    {
        if ($schema->hasTrait(Schema::T_SENSITIVE)) {
            return true;
        }

        $target = $schema->resolveTarget();

        return $target !== $schema
            && $target->hasTrait(Schema::T_SENSITIVE);
    }

    private static function safeUnknownValue(mixed $value): mixed
    {
        return \is_object($value)
            ? '<' . $value::class . '>'
            : self::REDACTED;
    }
}
