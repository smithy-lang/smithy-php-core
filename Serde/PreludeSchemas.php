<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

use Smithy\Core\Shape\ShapeType;

/**
 * Singleton schemas for the `smithy.api#*` prelude types.
 *
 * Loaded on file include. Reused across all services so a `smithy.api#String` member target does
 * not construct a new Schema per shape.
 *
 * @see https://smithy.io/2.0/spec/model.html#prelude
 *
 * @internal Codegen boot cache. Reach these through the generated shape's `schema()` method rather
 *                   than holding the singletons directly.
 */
final class PreludeSchemas
{
    public static Schema $STRING;
    public static Schema $INTEGER;
    public static Schema $LONG;
    public static Schema $SHORT;
    public static Schema $BYTE;
    public static Schema $FLOAT;
    public static Schema $DOUBLE;
    public static Schema $BOOLEAN;
    public static Schema $BLOB;
    public static Schema $TIMESTAMP;
    public static Schema $BIG_INTEGER;
    public static Schema $BIG_DECIMAL;
    public static Schema $DOCUMENT;

    private static bool $initialized = false;

    /**
     * Initialize all prelude schema singletons.
     *
     * Called at file-load time (bottom of this file). Idempotent, so callers can invoke it
     * explicitly to force eager initialization.
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$STRING = new Schema(
            id: 'smithy.api#String',
            type: ShapeType::String_
        );
        self::$INTEGER = new Schema(
            id: 'smithy.api#Integer',
            type: ShapeType::Integer
        );
        self::$LONG = new Schema(id: 'smithy.api#Long', type: ShapeType::Long);
        self::$SHORT = new Schema(
            id: 'smithy.api#Short',
            type: ShapeType::Short
        );
        self::$BYTE = new Schema(id: 'smithy.api#Byte', type: ShapeType::Byte);
        self::$FLOAT = new Schema(
            id: 'smithy.api#Float',
            type: ShapeType::Float_
        );
        self::$DOUBLE = new Schema(
            id: 'smithy.api#Double',
            type: ShapeType::Double
        );
        self::$BOOLEAN = new Schema(
            id: 'smithy.api#Boolean',
            type: ShapeType::Boolean
        );
        self::$BLOB = new Schema(id: 'smithy.api#Blob', type: ShapeType::Blob);
        self::$TIMESTAMP = new Schema(
            id: 'smithy.api#Timestamp',
            type: ShapeType::Timestamp
        );
        self::$BIG_INTEGER = new Schema(
            id: 'smithy.api#BigInteger',
            type: ShapeType::BigInteger
        );
        self::$BIG_DECIMAL = new Schema(
            id: 'smithy.api#BigDecimal',
            type: ShapeType::BigDecimal
        );
        self::$DOCUMENT = new Schema(
            id: 'smithy.api#Document',
            type: ShapeType::Document
        );

        self::$initialized = true;
    }

    /**
     * Return the prelude schema for `$shapeId`, or null when it is not a prelude type.
     *
     * @param string $shapeId Fully qualified shape ID, e.g. "smithy.api#String".
     *
     * @return Schema|null Schema singleton for the prelude type, or null when `$shapeId` does not
     *                     name a prelude shape.
     */
    public static function get(string $shapeId): ?Schema
    {
        self::init();

        return match ($shapeId) {
            'smithy.api#String' => self::$STRING,
            'smithy.api#Integer' => self::$INTEGER,
            'smithy.api#Long' => self::$LONG,
            'smithy.api#Short' => self::$SHORT,
            'smithy.api#Byte' => self::$BYTE,
            'smithy.api#Float' => self::$FLOAT,
            'smithy.api#Double' => self::$DOUBLE,
            'smithy.api#Boolean' => self::$BOOLEAN,
            'smithy.api#Blob' => self::$BLOB,
            'smithy.api#Timestamp' => self::$TIMESTAMP,
            'smithy.api#BigInteger' => self::$BIG_INTEGER,
            'smithy.api#BigDecimal' => self::$BIG_DECIMAL,
            'smithy.api#Document' => self::$DOCUMENT,
            default => null,
        };
    }

    /**
     * Return true when `$shapeId` names a Smithy prelude type.
     *
     * @param string $shapeId Fully qualified shape ID, e.g. "smithy.api#String".
     *
     * @return bool True when `$shapeId` is in the `smithy.api#` namespace and names one of the
     *              built-in prelude types.
     */
    public static function isPreludeShape(string $shapeId): bool
    {
        return \str_starts_with($shapeId, 'smithy.api#')
            && self::get($shapeId) !== null;
    }
}

// Eagerly initialize prelude schemas on file load
PreludeSchemas::init();
