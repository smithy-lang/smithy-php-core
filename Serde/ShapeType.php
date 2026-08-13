<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Smithy shape types.
 *
 * Int-backed so {@see Schema::$type} (which stores `$type->value`) can be
 * compared with literal-int `case` arms in serializer and deserializer
 * dispatch. Opcache lowers that to a packed jump table (`ZEND_SWITCH_LONG`)
 * rather than a chain of strict-equality opcodes.
 *
 * Codegen emits these as `ShapeType::Foo` in generated Schema literals.
 * The Schema constructor unwraps to the int once at construction.
 *
 * The integer values are implementation detail. Downstream dispatch
 * switches encode them as literals and must be updated together.
 */
enum ShapeType: int
{
    case Structure = 1;
    case Union = 2;
    case Enum = 3;
    case IntEnum = 4;
    case List_ = 5;
    case Map = 6;
    case String_ = 7;
    case Integer = 8;
    case Long = 9;
    case Short = 10;
    case Byte = 11;
    case Float_ = 12;
    case Double = 13;
    case Boolean = 14;
    case Timestamp = 15;
    case Blob = 16;
    case BigInteger = 17;
    case BigDecimal = 18;
    case Document = 19;
    case Operation = 20;
    case Service = 21;
    case Resource = 22;
}
