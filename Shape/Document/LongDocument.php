<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy long value (64-bit integer).
 *
 * Dispatches through the same `serializeInteger()` wire path as
 * {@see IntegerDocument}. The attached schema's type distinguishes long from
 * integer at the codec boundary.
 *
 * On 64-bit PHP an `int` already covers the Smithy long range. On 32-bit PHP
 * the caller must use {@see BigIntegerDocument} for values that exceed
 * `PHP_INT_MAX`.
 */
final class LongDocument extends IntegerDocument
{
    public function type(): ShapeType
    {
        return ShapeType::Long;
    }
}
