<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

/**
 * Smithy shape types.
 *
 * Int-backed so {@see \Smithy\Core\Serde\Schema::$type} can be compared with
 * literal-int `case` arms in serializer and deserializer dispatch. Opcache
 * lowers that to a packed jump table (`ZEND_SWITCH_LONG`) rather than a chain
 * of strict-equality opcodes.
 *
 * Codegen emits these as `ShapeType::Foo` in generated Schema literals. The Schema constructor
 * unwraps to the int once at construction.
 *
 * # Ordering
 *
 * Cases are grouped by Smithy category and, within each group, ordered ascending by domain size /
 * complexity:
 *
 *  Scalars: `Boolean`, then integer widths (`Byte` → `Long`),
 *      float widths (`Float_` → `Double`), arbitrary-precision numbers
 *      (`BigInteger`, `BigDecimal`), character data (`String_`, `Blob`),
 *      temporal (`Timestamp`).
 *  Aggregates: `List_`, `Map`.
 *  User-defined aggregates: `Structure`, `Union`, `Enum`, `IntEnum`.
 *  Polymorphic: `Document`.
 *  Service metadata: `Operation`, `Service`, `Resource`.
 *
 * The integer values are implementation detail, never persisted, never sent on the wire. Downstream
 * dispatch switches encode them as literals and must be updated together with any reassignment.
 */
enum ShapeType: int
{
    case Boolean = 1;
    case Byte = 2;
    case Short = 3;
    case Integer = 4;
    case Long = 5;
    case Float_ = 6;
    case Double = 7;
    case BigInteger = 8;
    case BigDecimal = 9;
    case String_ = 10;
    case Blob = 11;
    case Timestamp = 12;
    case List_ = 13;
    case Map = 14;
    case Structure = 15;
    case Union = 16;
    case Enum = 17;
    case IntEnum = 18;
    case Document = 19;
    case Operation = 20;
    case Service = 21;
    case Resource = 22;
}
