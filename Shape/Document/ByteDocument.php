<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Shape\ShapeType;

/**
 * Document holding a Smithy byte value (8-bit integer).
 *
 * Range-checks the value at construction.
 */
final class ByteDocument extends IntegerDocument
{
    /**
     * Build a byte document.
     *
     * @param int $value Byte payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     *
     * @throws IncompatibleDocumentTypeException When $value is outside the signed 8-bit range.
     */
    public function __construct(int $value, ?Schema $schema = null)
    {
        if ($value < -128 || $value > 127) {
            throw new IncompatibleDocumentTypeException(
                "Value {$value} does not fit in a byte (8-bit signed integer)."
            );
        }

        parent::__construct($value, $schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Byte document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Byte;
    }
}
