<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Shape\ShapeType;

/**
 * Lazy-initialized schemas for Smithy's prelude shapes (`smithy.api#String`,
 * `smithy.api#Integer`, and so on).
 *
 * Codec serializers expect a {@see Schema} on every typed `serialize*()` call. An untyped document
 * falls back to the prelude schema for its data-model type.
 */
final class PreludeSchemas
{
    /**
     * @var array<int, Schema> Cache keyed by {@see ShapeType} integer value.
     */
    private static array $cache = [];

    /**
     * Return the prelude schema for the given data-model type, building it on first request.
     *
     * @param ShapeType $type Data-model type to resolve.
     *
     * @return Schema Prelude schema for the type.
     */
    public static function for(ShapeType $type): Schema
    {
        return self::$cache[$type->value] ??= self::create($type);
    }

    /**
     * Build the prelude schema for the given data-model type.
     *
     * @param ShapeType $type Data-model type to build.
     *
     * @return Schema Prelude schema for the type.
     */
    private static function create(ShapeType $type): Schema
    {
        $id = 'smithy.api#' . self::shapeIdSegment($type);

        return match ($type) {
            ShapeType::List_ => new Schema(
                id: $id,
                type: $type,
                listMember: self::for(ShapeType::Document),
                traits: [Schema::T_SPARSE => true]
            ),
            ShapeType::Map => new Schema(
                id: $id,
                type: $type,
                mapKey: self::for(ShapeType::String_),
                mapValue: self::for(ShapeType::Document),
                traits: [Schema::T_SPARSE => true]
            ),
            default => new Schema(
                id: $id,
                type: $type
            )
        };
    }

    /**
     * Map a {@see ShapeType} case to the corresponding shape-ID segment.
     *
     * @param ShapeType $type Data-model type to map.
     *
     * @return string Shape-ID segment (the part after `smithy.api#`).
     */
    private static function shapeIdSegment(ShapeType $type): string
    {
        return match ($type) {
            ShapeType::String_ => 'String',
            ShapeType::Boolean => 'Boolean',
            ShapeType::Byte => 'Byte',
            ShapeType::Short => 'Short',
            ShapeType::Integer => 'Integer',
            ShapeType::Long => 'Long',
            ShapeType::Float_ => 'Float',
            ShapeType::Double => 'Double',
            ShapeType::BigInteger => 'BigInteger',
            ShapeType::BigDecimal => 'BigDecimal',
            ShapeType::Blob => 'Blob',
            ShapeType::Timestamp => 'Timestamp',
            ShapeType::List_ => 'List',
            ShapeType::Map => 'Map',
            ShapeType::Document => 'Document',
            ShapeType::Structure => 'Structure',
            ShapeType::Union => 'Union',
            ShapeType::Enum => 'Enum',
            ShapeType::IntEnum => 'IntEnum'
        };
    }
}
