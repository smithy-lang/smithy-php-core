<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Computes extension data for a given schema.
 *
 * Providers are invoked lazily on first access to a schema's extension slot via
 * `Schema::getExtension()`. The returned value is cached on the schema (immutable after first
 * computation).
 */
interface SchemaExtensionProviderInterface
{
    /**
     * Compute extension data for `$schema`.
     *
     * @param Schema $schema Schema instance the extension is being computed for.
     *
     * @return mixed Value to cache on the schema. May be null.
     */
    public function provide(Schema $schema): mixed;
}
