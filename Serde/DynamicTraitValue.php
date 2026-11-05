<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Preserve the value of an unregistered runtime trait.
 */
final readonly class DynamicTraitValue
{
    /**
     * @param string $id Fully-qualified trait shape ID.
     * @param mixed $value Raw trait node value.
     */
    public function __construct(
        public string $id,
        public mixed $value
    ) {}
}
