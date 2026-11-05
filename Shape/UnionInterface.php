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
 * Interface implemented by generated Smithy union shapes.
 *
 * Smithy unions are tagged. Exactly one variant is active at a time. The interface lets
 * serializers and deserializers determine the active variant and its value without knowing the
 * concrete type.
 *
 * Extends {@see SerializableShapeInterface} so unions are encodable through the universal
 * `serialize()` entry point. Generated unions emit a `serialize()` body in the abstract base
 * class that dispatches into the codec's `serializeUnion()`.
 */
interface UnionInterface extends SerializableShapeInterface
{
    /**
     * Return the schema describing this union instance.
     *
     * Generated implementations delegate to {@see schema()}, preserving
     * their process-wide lazy-static cache. Runtime implementations may
     * instead carry the schema on the instance.
     *
     * @return Schema Union shape schema.
     */
    public function getSchema(): Schema;

    /**
     * Return the schema member index of the active variant.
     *
     * Unknown or unmodeled variants return -1.
     */
    public function getMemberIndex(): int;

    /**
     * Return the tag name of the active variant (the Smithy member name).
     *
     * For a union with members `stringValue`, `intValue`, `event`:
     *
     *   - The stringValue variant returns `stringValue`.
     *   - The event variant returns `event`.
     *   - Unknown or unmodeled variants return the raw tag string.
     *
     * @return string Active variant tag.
     */
    public function tag(): string;

    /**
     * Return the value of the active variant.
     *
     * The runtime type depends on which variant is active. It may be a scalar, an enum, a
     * structure, or any other Smithy type.
     *
     * @return mixed Active variant value.
     */
    public function value(): mixed;
}
