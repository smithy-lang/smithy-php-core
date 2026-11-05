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
 * Document holding a Smithy boolean value.
 */
final class BooleanDocument extends AbstractDocument
{
    /**
     * Build a boolean document.
     *
     * @param bool $value Boolean payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     */
    public function __construct(
        public readonly bool $value,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType Boolean document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::Boolean;
    }

    /**
     * Return the wrapped boolean.
     *
     * @return bool Boolean payload.
     */
    public function asBoolean(): bool
    {
        return $this->value;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeBoolean()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBoolean(
            $this->schema ?? PreludeSchemas::for(ShapeType::Boolean),
            $this->value,
            $idx
        );
    }
}
