<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;

/**
 * Interface implemented by generated Smithy operation metadata classes.
 *
 * Generated implementations are codegen-emitted singletons obtained through the implementing
 * class's `instance()` accessor. The instance carries no per-call state. One instance per
 * operation lives for the lifetime of the process. Tests can hand-roll a minimal implementation
 * that overrides only the methods the test exercises (mirrors smithy-java's test pattern).
 *
 * Method categories:
 *
 *   - Literal-value getters (`getName`, `getId`, `getInputClass`, `getOutputClass`,
 *     `getErrors`) are instance methods. Generated classes return a `public const` so the body
 *     collapses to a constant load.
 *   - Schema accessors (`schema`, `inputSchema`, `outputSchema`) are static. The bare names
 *     mirror {@see SerializableStructInterface::schema()} and smithy-java's
 *     `ApiOperation::schema()`.
 *   - Optional member-scan getters (`getIdempotencyTokenMember`, `getInputStreamMember`,
 *     `getOutputStreamMember`) are static. Codegen resolves the member at generation time and
 *     emits either `return null;` or a direct member-by-index lookup against the input or
 *     output schema.
 *
 * HTTP-binding operations also implement `HttpBindingOperationInterface`, which adds a typed
 * `schemaExtension(): HttpOperationBindingExtension` accessor that bypasses the schema's
 * extension hash lookup. Non-HTTP operations (RPC, query) reach any schema extensions through
 * `schema()->getExtension($key)`.
 */
interface OperationInterface
{
    /**
     * Return the operation name (e.g., "AssumeRole").
     *
     * @return string Operation name.
     */
    public function getName(): string;

    /**
     * Return the fully-qualified Smithy shape ID of the operation.
     *
     * @return string Operation shape ID.
     */
    public function getId(): string;

    /**
     * Return the input shape class name.
     *
     * @return class-string<SerializableStructInterface> Fully-qualified input class name.
     */
    public function getInputClass(): string;

    /**
     * Return the output shape class name.
     *
     * @return class-string<SerializableStructInterface> Fully-qualified output class name.
     */
    public function getOutputClass(): string;

    /**
     * Return the error shape class names this operation can return.
     *
     * @return list<class-string<SerializableStructInterface>> Error class names.
     */
    public function getErrors(): array;

    /**
     * Return the schema for the operation itself.
     *
     * Carries operation-level traits (`@http`, `@errors`, `@auth`, and so on).
     *
     * @return Schema Operation schema.
     */
    public static function schema(): Schema;

    /**
     * Return the schema for the operation's input (request) shape.
     *
     * @return Schema Input shape schema.
     */
    public static function inputSchema(): Schema;

    /**
     * Return the schema for the operation's output (response) shape.
     *
     * @return Schema Output shape schema.
     */
    public static function outputSchema(): Schema;

    /**
     * Return the input member that carries the request body stream (`@streaming`), or null when
     * the operation has no streaming input.
     *
     * @return Schema|null Streaming input member schema, or null.
     */
    public static function getInputStreamMember(): ?Schema;

    /**
     * Return the output member that carries the response body stream (`@streaming`), or null when
     * the operation has no streaming output.
     *
     * @return Schema|null Streaming output member schema, or null.
     */
    public static function getOutputStreamMember(): ?Schema;
}
