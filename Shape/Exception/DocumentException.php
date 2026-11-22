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
use Smithy\Core\Shape\Document\StructDocument;

/**
 * Represent a runtime-loaded error shape as a modeled exception.
 */
final class DocumentException extends ApiException
    implements SerializableStructInterface
{
    use DocumentExceptionTrait;

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
     * Return a copy with one modeled member replaced.
     */
    public function withMemberValue(Schema $member, mixed $value): static
    {
        $copy = new self(
            $this->getSchema(),
            [],
            $this->getMessage(),
            $this->fault,
            $this->getPrevious()
        );
        $copy->document = $this->document->withMemberValue($member, $value);

        return $copy;
    }
}
