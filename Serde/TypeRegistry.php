<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

use Smithy\Core\Model\SmithyModel;

/**
 * Lazily resolve runtime model shape IDs to schemas.
 *
 * The registry maps shape IDs to schemas only, so {@see classFor()} returns null and
 * {@see deserialize()} reports every discriminator as unregistered.
 */
final class TypeRegistry implements TypeRegistryInterface
{
    use TypeRegistryDeserializeTrait;

    /** @var array<string, Schema> */
    private array $schemas = [];

    /** @var array<string, Schema> */
    private array $schemasById = [];

    /**
     * Create a registry backed by a runtime model converter.
     */
    public function __construct(
        private readonly SmithyModel $model,
        private readonly SchemaConverter $converter
    ) {}

    /**
     * Resolve a modeled shape ID, or return null when it is unknown.
     */
    public function schemaFor(string $shapeId): ?Schema
    {
        if ($this->model->getShape($shapeId) === null) {
            return null;
        }
        if (isset($this->schemas[$shapeId])) {
            return $this->schemas[$shapeId];
        }

        $canonicalId = $this->model->getOriginalShapeId($shapeId);
        $conversionId = (
            $canonicalId !== $shapeId
            && $this->model->getShape($canonicalId) !== null
        )
            ? $canonicalId
            : $shapeId;

        if ($conversionId === $canonicalId
            && isset($this->schemas[$canonicalId])
        ) {
            return $this->schemas[$shapeId] = $this->schemas[$canonicalId];
        }
        if (isset($this->schemas[$conversionId])) {
            return $this->schemas[$shapeId] = $this->schemas[$conversionId];
        }

        $schema = $this->converter->getSchema($conversionId);
        $this->schemas[$shapeId] = $schema;
        $this->schemas[$schema->id] ??= $schema;
        $this->schemasById[$conversionId] ??= $schema;

        return $schema;
    }

    /**
     * Return null. Runtime models have no generated classes.
     *
     * @return class-string|null
     */
    public function classFor(string $shapeId): ?string
    {
        return null;
    }

    /**
     * Return the schemas resolved by this registry.
     *
     * @return array<string, Schema> Canonical shape ID to schema.
     */
    public function all(): array
    {
        return $this->schemasById;
    }
}
