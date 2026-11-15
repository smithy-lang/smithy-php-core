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
use Smithy\Core\Shape\BigInteger;

/**
 * Document holding a Smithy bigInteger value.
 *
 * Wraps a {@see BigInteger} to preserve precision beyond PHP's native `int`
 * range.
 */
final class BigIntegerDocument extends AbstractDocument
{
    /**
     * Build a bigInteger document.
     *
     * @param BigInteger $value Integer payload.
     * @param Schema|null $schema Schema to attach, or null for an untyped document.
     */
    public function __construct(
        public readonly BigInteger $value,
        ?Schema $schema = null
    )
    {
        parent::__construct($schema);
    }

    /**
     * Return the document type.
     *
     * @return ShapeType BigInteger document type.
     */
    public function type(): ShapeType
    {
        return ShapeType::BigInteger;
    }

    /**
     * Return the wrapped integer.
     *
     * @return BigInteger Integer payload.
     */
    public function asBigInteger(): BigInteger
    {
        return $this->value;
    }

    /**
     * Dispatch into {@see ShapeSerializerInterface::serializeBigInteger()}.
     *
     * @return void
     */
    public function serialize(ShapeSerializerInterface $serializer, int $idx): void
    {
        $serializer->serializeBigInteger(
            $this->schema ?? PreludeSchemas::for(ShapeType::BigInteger),
            $this->value,
            $idx
        );
    }
}
