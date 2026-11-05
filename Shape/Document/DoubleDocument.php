<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Shape\ShapeType;

/**
 * Document holding a Smithy double value.
 *
 * Same wire dispatch as {@see FloatDocument} through `serializeFloat()`. The
 * attached schema's type distinguishes double from float at the codec
 * boundary.
 */
final class DoubleDocument extends FloatDocument
{
    /**
     * Return the document type.
     *
     * @return ShapeType Double document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Double;
    }
}
