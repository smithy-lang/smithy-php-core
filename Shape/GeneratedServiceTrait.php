<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;

/**
 * Instance metadata adapters for generated service classes.
 */
trait GeneratedServiceTrait
{
    private static ?ServiceInterface $serviceInstance = null;

    /** @var list<OperationInterface>|null */
    private static ?array $operationInstances = null;

    /**
     * Return the generated service schema.
     */
    abstract public static function schema(): Schema;

    /**
     * Return the shared generated service instance.
     */
    public static function instance(): static
    {
        return self::$serviceInstance ??= new static();
    }

    /**
     * Return the generated service name.
     */
    public function getName(): string
    {
        return static::name();
    }

    /**
     * Return the generated service shape ID.
     */
    public function getId(): string
    {
        return static::id();
    }

    /**
     * Return the generated service version.
     */
    public function getVersion(): string
    {
        return static::version();
    }

    /**
     * Return the generated service schema.
     */
    public function getSchema(): Schema
    {
        return static::schema();
    }

    /**
     * Return every generated operation instance.
     *
     * @return list<OperationInterface> Service operations.
     */
    public function getOperations(): array
    {
        if (self::$operationInstances !== null) {
            return self::$operationInstances;
        }

        $operations = [];
        foreach (static::operations() as $operationClass) {
            $operations[] = $operationClass::instance();
        }

        return self::$operationInstances = $operations;
    }

    /**
     * Resolve a generated operation by name.
     */
    public function getOperation(string $name): ?OperationInterface
    {
        foreach ($this->getOperations() as $operation) {
            if ($operation->getName() === $name) {
                return $operation;
            }
        }

        return null;
    }
}
