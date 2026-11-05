<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;

/**
 * Modeled Smithy operation metadata.
 *
 * Generated implementations are codegen-emitted singletons obtained through
 * the implementing class's `instance()` accessor. The instance carries no
 * per-call state. One instance per operation lives for the lifetime of the
 * process. Tests can hand-roll a minimal implementation overriding only the
 * methods the test exercises (mirrors smithy-java's test pattern).
 *
 * Method shape:
 *
 *   - Literal-value getters are instance methods. Generated classes return
 *     a `public const` so the body collapses to a constant load.
 *   - `getRuntimeIdentity()` provides a stable, process-local identity
 *     for per-operation runtime caches.
 *   - Modeled errors resolve to schemas through `resolveErrorSchema()`.
 *   - Schema metadata is exposed through instance accessors. Generated
 *     operations may retain static schema methods as an implementation
 *     optimization.
 *   - Optional stream-member getters (`getInputStreamMember`,
 *     `getOutputStreamMember`) are instance methods. Codegen resolves the
 *     member at generation time and emits either `return null` or a direct
 *     member-by-index lookup against the input or output schema.
 *
 * HTTP-binding operations also implement
 * {@see HttpBindingOperationInterface}, which provides direct access to
 * HTTP-specific schema metadata.
 */
interface OperationInterface
{
    /**
     * Return the operation name, such as "AssumeRole".
     */
    public function getName(): string;

    /**
     * Return the fully-qualified Smithy shape ID of the operation.
     */
    public function getId(): string;

    /**
     * Return the fully-qualified Smithy service shape ID.
     */
    public function getServiceId(): ?string;

    /**
     * Return the concrete exception class used for unmodeled service errors.
     *
     * Its public constructor must accept a string message, an
     * {@see \Smithy\Core\Exception\ErrorFault}, and an optional nullable
     * Throwable for exception chaining, in that order.
     *
     * @return class-string<\Smithy\Core\Exception\ApiException>
     */
    public function getServiceExceptionClass(): string;

    /**
     * Return the stable identity used by per-operation runtime caches.
     *
     * Generated operations return their class name. Runtime operations
     * return an identity scoped to the model that created them.
     */
    public function getRuntimeIdentity(): string;

    /**
     * Authentication scheme shape IDs effective for this operation.
     *
     * @return list<string> Scheme IDs in priority order.
     */
    public function getEffectiveAuthSchemes(): array;

    /**
     * Return the schema for the operation itself.
     *
     * Carries operation-level traits (`@http`, `@errors`, `@auth`, etc.).
     */
    public function getSchema(): Schema;

    /**
     * Return the schema for the input request shape.
     */
    public function getInputSchema(): Schema;

    /**
     * Return the schema for the output response shape.
     */
    public function getOutputSchema(): Schema;

    /**
     * Return the service and operation errors effective for this operation.
     *
     * @return list<Schema> Modeled error schemas.
     */
    public function getErrorSchemas(): array;

    /**
     * Create an operation input from positional member values.
     *
     * @param array<int, mixed> $members Positional input member values.
     *
     * @return SerializableStructInterface Materialized input.
     */
    public function createInput(array $members): SerializableStructInterface;

    /**
     * Create an operation output from positional member values.
     *
     * Generated operations construct their generated output class directly.
     * Runtime operations construct a schema-backed structure document.
     *
     * @param array<int, mixed> $members Positional output member values.
     *
     * @return SerializableStructInterface Materialized output.
     */
    public function createOutput(array $members): SerializableStructInterface;

    /**
     * Return the protocol request target, when the selected protocol uses one.
     *
     * @return string|null Request target, or null for protocols without one.
     */
    public function getRequestTarget(): ?string;

    /**
     * Resolve a wire error discriminator to its modeled schema.
     *
     * @return Schema|null Modeled error schema, or null when unrecognized.
     */
    public function resolveErrorSchema(string $discriminator): ?Schema;

    /**
     * Return the input member that carries the streaming request body.
     */
    public function getInputStreamMember(): ?Schema;

    /**
     * Return the output member that carries the streaming response body.
     */
    public function getOutputStreamMember(): ?Schema;
}
