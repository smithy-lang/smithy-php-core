<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Lazily resolve a schema reference.
 *
 * Runtime model conversion uses references to close recursive shape graphs
 * before every target schema has been materialized. The resolved schema is
 * cached after the first access.
 */
final class SchemaReference
{
    private ?Schema $resolved = null;

    /**
     * @param \Closure(): Schema $resolver Resolves the referenced schema.
     */
    public function __construct(
        private readonly \Closure $resolver
    ) {}

    /**
     * Resolve and cache the referenced schema.
     *
     * @return Schema Referenced schema.
     */
    public function resolve(): Schema
    {
        return $this->resolved ??= ($this->resolver)();
    }
}
