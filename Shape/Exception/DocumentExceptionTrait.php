<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Exception;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\Document\StructDocument;

/**
 * Document access for schema-backed modeled exceptions.
 *
 * The using class declares a `private StructDocument $document` property and
 * implements {@see \Smithy\Core\Serde\SerializableStructInterface}.
 */
trait DocumentExceptionTrait
{
    /**
     * Return the modeled error schema.
     */
    public function getSchema(): Schema
    {
        return $this->document->getSchema();
    }

    /**
     * Return the modeled error contents.
     */
    public function getContents(): StructDocument
    {
        return $this->document;
    }

    /**
     * Serialize modeled error members.
     */
    public function serializeMembers(ShapeSerializerInterface $serializer): void
    {
        $this->document->serializeMembers($serializer);
    }

    /**
     * Serialize this modeled error.
     */
    public function serialize(
        ShapeSerializerInterface $serializer,
        int $idx
    ): void
    {
        $this->document->serialize($serializer, $idx);
    }

    /**
     * Return one modeled member value.
     */
    public function getMemberValue(Schema $member): mixed
    {
        return $this->document->getMemberValue($member);
    }
}
