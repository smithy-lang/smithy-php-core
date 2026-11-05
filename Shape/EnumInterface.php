<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableShapeInterface;

/**
 * Interface implemented by generated Smithy enum and intEnum value objects.
 *
 * Carries the enum's backing scalar through {@see getValue()} and brings the shape under the
 * universal `serialize()` contract through {@see SerializableShapeInterface}. Generated classes
 * expose {@see schema()} as a static so the codec hot path can dispatch through `serializeString()`
 * or `serializeInteger()` with the enum's own schema when the enum serializes standalone (outside
 * a parent struct's `serializeMembers()`).
 */
interface EnumInterface extends SerializableShapeInterface
{
    /**
     * Return the schema describing this enum instance.
     *
     * Generated implementations delegate to {@see schema()}, preserving
     * their process-wide lazy-static cache. Runtime implementations may
     * instead carry the schema on the instance.
     *
     * @return Schema Enum shape schema.
     */
    public function getSchema(): Schema;

    /**
     * Return the backing scalar value.
     *
     * @return string|int String for enum shapes, int for intEnum shapes.
     */
    public function getValue(): string|int;
}
