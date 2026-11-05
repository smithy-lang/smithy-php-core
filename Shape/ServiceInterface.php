<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;

/**
 * Provide runtime service metadata.
 */
interface ServiceInterface
{
    /**
     * Return the service name, such as "STS" or "DynamoDB".
     */
    public function getName(): string;

    /**
     * Return the fully-qualified Smithy shape ID of the service.
     */
    public function getId(): string;

    /**
     * Return the service version.
     */
    public function getVersion(): string;

    /**
     * Return the schema for the service shape.
     */
    public function getSchema(): Schema;

    /**
     * List every operation in this service.
     *
     * @return list<OperationInterface> Service operations.
     */
    public function getOperations(): array;

    /**
     * Resolve an operation by its modeled name.
     *
     * @return OperationInterface|null Operation metadata, or null when absent.
     */
    public function getOperation(string $name): ?OperationInterface;
}
