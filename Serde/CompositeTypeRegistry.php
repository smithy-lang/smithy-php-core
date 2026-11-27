<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Combine type registries. Lookups consult the registries in order and the first match wins.
 */
final class CompositeTypeRegistry implements TypeRegistryInterface
{
    use TypeRegistryDeserializeTrait;

    /**
     * @param list<TypeRegistryInterface> $registries Registries in lookup order.
     */
    private function __construct(private readonly array $registries) {}

    /**
     * Combine registries into one.
     *
     * @param TypeRegistryInterface $first Registry consulted first.
     * @param TypeRegistryInterface $second Registry consulted after `$first`.
     * @param TypeRegistryInterface ...$rest Registries consulted after `$second`, in order.
     */
    public static function compose(
        TypeRegistryInterface $first,
        TypeRegistryInterface $second,
        TypeRegistryInterface ...$rest
    ): self
    {
        // Named arguments give the variadic string keys.
        return new self([$first, $second, ...\array_values($rest)]);
    }

    /**
     * Resolve a shape ID through the first registry that knows it.
     */
    public function schemaFor(string $shapeId): ?Schema
    {
        foreach ($this->registries as $registry) {
            $schema = $registry->schemaFor($shapeId);
            if ($schema !== null) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * Resolve a shape ID to a class through the first registry that has one.
     */
    public function classFor(string $shapeId): ?string
    {
        foreach ($this->registries as $registry) {
            $class = $registry->classFor($shapeId);
            if ($class !== null) {
                return $class;
            }
        }

        return null;
    }
}
