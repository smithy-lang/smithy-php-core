<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

use Smithy\Core\Shape\Document\DocumentInterface;
use Smithy\Core\Shape\Exception\InvalidShapeValueException;

/**
 * Map Smithy shape IDs to schemas and generated classes.
 *
 * Use {@see CompositeTypeRegistry::compose()} to combine registries.
 */
interface TypeRegistryInterface
{
    /**
     * Resolve a shape ID to its schema.
     *
     * @param string $shapeId Fully-qualified Smithy shape ID.
     *
     * @return Schema|null Schema, or null when the ID is not registered.
     */
    public function schemaFor(string $shapeId): ?Schema;

    /**
     * Return the generated class name for a shape ID, or null when the ID has no registered class.
     *
     * @param string $shapeId Fully-qualified Smithy shape ID.
     */
    public function classFor(string $shapeId): ?string;

    /**
     * Build the registered shape that a document's discriminator names.
     *
     * @param DocumentInterface $document Document carrying a discriminator.
     *
     * @return object Hydrated shape instance.
     * @throws InvalidShapeValueException When the document has no discriminator or the
     *         discriminator has no registered class.
     */
    public function deserialize(DocumentInterface $document): object;
}
