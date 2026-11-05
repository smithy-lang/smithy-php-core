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
 * Document holding a Smithy blob value as raw bytes.
 */
class BlobDocument extends AbstractDocument
{
    /**
     * Build a blob document.
     *
     * @param string $bytes Blob payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     */
    public function __construct(
        public readonly string $bytes,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Blob document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Blob;
    }

    /**
     * Return the raw blob bytes.
     *
     * @return string Blob payload.
     */
    public function asBlob(): string
    {
        return $this->bytes;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeBlob()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBlob(
            $this->schema ?? PreludeSchemas::for($this->type()),
            $this->bytes,
            $idx
        );
    }
}
