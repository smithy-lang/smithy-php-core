<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Exception;

use Smithy\Core\Retry\RetryInfoInterface;
use Smithy\Core\Retry\RetrySafety;

/**
 * Base exception for all API call errors.
 *
 * Carries an {@see ErrorFault} classification and serves as the default parent for generated
 * modeled error shapes when no service-specific exception base is configured.
 *
 * Implements {@see RetryInfoInterface} so {@see \Smithy\Client\Retry\StandardRetryStrategy}
 * classifies any API error directly from the exception instead of the network/5xx fallback.
 * The default classification comes from the fault (Server -> Yes, Client -> No, Other -> Maybe);
 * subclasses and codegen may override it with richer wire information (HTTP status, the
 * {@see https://smithy.io/2.0/spec/behavior-traits.html#smithy-api-retryable-trait retryable trait}).
 */
class ApiException extends \RuntimeException implements RetryInfoInterface
{
    /**
     * Server-suggested wait before retrying, in seconds, or null for no hint.
     *
     * Set from a modeled classification or a protocol `Retry-After` header.
     */
    protected ?float $retryAfter = null;

    /**
     * Explicit retry safety that overrides the fault-derived default when set.
     */
    protected ?RetrySafety $retrySafetyOverride = null;

    /**
     * Explicit throttle flag that overrides the default when set.
     */
    protected ?bool $throttleOverride = null;

    /**
     * @param string $message Human-readable error message.
     * @param ErrorFault $fault Fault classification for this error. Defaults to
     *                   {@see ErrorFault::Other} when the source is neither clearly client- nor
     *                   server-side.
     * @param \Throwable|null $previous Underlying exception for chaining, if any.
     *
     * @return void
     */
    public function __construct(
        string $message = '',
        public readonly ErrorFault $fault = ErrorFault::Other,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Return true when the fault is classified as client-side.
     *
     * @return bool True when `$fault` is {@see ErrorFault::Client}.
     */
    public function isClientFault(): bool
    {
        return $this->fault === ErrorFault::Client;
    }

    /**
     * Return true when the fault is classified as server-side.
     *
     * @return bool True when `$fault` is {@see ErrorFault::Server}.
     */
    public function isServerFault(): bool
    {
        return $this->fault === ErrorFault::Server;
    }

    /**
     * Classify whether it is safe to retry this error.
     *
     * An explicit override (set by codegen for a modeled `retryable` error, or by a subclass
     * from the HTTP status) wins. Otherwise the fault decides: Server -> Yes, Client -> No,
     * Other -> Maybe (treated as not retryable).
     */
    public function getRetrySafety(): RetrySafety
    {
        if ($this->retrySafetyOverride !== null) {
            return $this->retrySafetyOverride;
        }

        return match ($this->fault) {
            ErrorFault::Server => RetrySafety::Yes,
            ErrorFault::Client => RetrySafety::No,
            ErrorFault::Other => RetrySafety::Maybe,
        };
    }

    /**
     * Return true when the failure is a throttling error.
     */
    public function isThrottle(): bool
    {
        return $this->throttleOverride ?? false;
    }

    /**
     * Return the server-suggested wait in seconds, or null for no hint.
     */
    public function getRetryAfter(): ?float
    {
        return $this->retryAfter;
    }
}
