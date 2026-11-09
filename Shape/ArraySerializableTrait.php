<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Shape\Utils\ArrayShapeSerializer;

/**
 * Convert generated structures to and from associative arrays.
 */
trait ArraySerializableTrait
{
    /**
     * Convert this structure to an associative array keyed by modeled member
     * names.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ArrayShapeSerializer::toArray($this);
    }

    /**
     * Create this structure from an associative array keyed by modeled member
     * names.
     *
     * @param array<string, mixed> $data
     *
     * @return static
     */
    public static function fromArray(array $data): static
    {
        /** @var static */
        return ArrayShapeSerializer::fromArray(static::class, $data);
    }
}
