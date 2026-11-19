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
 * Implement {@see TypeRegistryInterface::deserialize()} for registries that map shape IDs to
 * generated classes through `classFor()`.
 */
trait TypeRegistryDeserializeTrait
{
    /**
     * Build the registered shape that a document's discriminator names.
     *
     * @param DocumentInterface $document Document carrying a discriminator.
     *
     * @return object Hydrated shape instance.
     * @throws InvalidShapeValueException When the document has no discriminator or the
     *         discriminator has no registered class.
     */
    public function deserialize(DocumentInterface $document): object
    {
        $shapeId = $document->discriminator();
        if ($shapeId === null) {
            throw new InvalidShapeValueException(
                'Cannot deserialize a document that has no discriminator.'
            );
        }

        $class = $this->classFor($shapeId);
        if ($class === null) {
            throw new InvalidShapeValueException(
                "No registered shape for discriminator '{$shapeId}'."
            );
        }

        return $document->asShape($class);
    }
}
