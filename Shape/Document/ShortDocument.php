<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeType;

/**
 * Document holding a Smithy short value (16-bit integer).
 *
 * Range-checks the value at construction.
 */
final class ShortDocument extends IntegerDocument
{
    /**
     * @throws IncompatibleDocumentTypeException When $value is outside the signed 16-bit range.
     */
    public function __construct(int $value, ?Schema $schema = null)
    {
        if ($value < -32768 || $value > 32767) {
            throw new IncompatibleDocumentTypeException(
                "Value {$value} does not fit in a short "
                    . '(16-bit signed integer).'
            );
        }

        parent::__construct($value, $schema);
    }

    public function type(): ShapeType
    {
        return ShapeType::Short;
    }
}
