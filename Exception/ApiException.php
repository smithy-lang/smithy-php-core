<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Exception;

/**
 * Base exception for all API call errors.
 *
 * Carries an {@see ErrorFault} classification and serves as the default parent for generated
 * modeled error shapes when no service-specific exception base is configured.
 */
class ApiException extends \RuntimeException
{
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
}
