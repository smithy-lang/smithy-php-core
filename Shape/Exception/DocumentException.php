<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Exception;

use Smithy\Core\Exception\ApiException;
use Smithy\Core\Exception\ErrorFault;
use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;
use Smithy\Core\Serde\ShapeSerializerInterface;
use Smithy\Core\Shape\Document\StructDocument;

/**
 * Represent a runtime-loaded error shape as a modeled exception.
 */
final class DocumentException extends ApiException
    implements SerializableStructInterface
{
    private StructDocument $document;

    /**
     * Create a schema-backed modeled exception.
     *
     * @param array<int, mixed> $members Positional modeled error members.
     */
    public function __construct(
        Schema $schema,
        array $members,
        string $message = '',
        ErrorFault $fault = ErrorFault::Other,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $fault, $previous);
        $this->document = StructDocument::fromMembers($schema, $members);
    }

    /**
     * Return the modeled error schema.
     */
    public function getSchema(): Schema
    {
        return $this->document->getSchema();
    }

    /**
     * Serialize modeled error members.
     *
     * @return void
     */
    public function serializeMembers(ShapeSerializerInterface $serializer): void
    {
        $this->document->serializeMembers($serializer);
    }

    /**
     * Serialize this modeled error.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
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

    /**
     * Return a copy with one modeled member replaced.
     */
    public function withMemberValue(Schema $member, mixed $value): static
    {
        $members = [];
        foreach ($this->getSchema()->getMembersByIndex() as $index => $schema) {
            $members[$index] = $this->getMemberValue($schema);
        }
        $members[$member->memberIndex] = $value;

        return new self(
            $this->getSchema(),
            $members,
            $this->getMessage(),
            $this->fault,
            $this->getPrevious()
        );
    }
}
