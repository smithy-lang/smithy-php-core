<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\ShapeType;

/**
 * Document holding a string-keyed map of documents.
 *
 * Also serves as the natural representation for an open JSON object whose
 * shape is unknown at modeling time. Values may be null. Call `getMember()`
 * to read entries by name without going through `asMap()`.
 */
final class MapDocument extends AbstractDocument
{
    /**
     * @param array<string, AbstractDocument|null> $entries Key-to-document entries.
     * @param Schema|null $schema Schema to attach, or null for an untyped map.
     */
    public function __construct(
        public readonly array $entries,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Map document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Map;
    }

    /**
     * Return the map entries.
     *
     * @return array<string, AbstractDocument|null> Key-to-document entries.
     */
    public function asMap(): array
    {
        return $this->entries;
    }

    /**
     * Return one named entry.
     *
     * @param string $name Entry name.
     *
     * @return AbstractDocument|null Entry document, or null when absent or explicitly null.
     */
    public function getMember(string $name): ?AbstractDocument
    {
        return $this->entries[$name] ?? null;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeMap()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeMap(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->entries,
            $idx
        );
    }
}
