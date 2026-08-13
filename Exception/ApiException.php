<?php

declare(strict_types=1);

namespace Smithy\Core\Exception;

/**
 * Base exception for all API call errors.
 *
 * Carries an {@see ErrorFault} classification and serves as the default
 * parent for generated modeled error shapes when no service-specific
 * exception base is configured.
 */
class ApiException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ErrorFault $fault = ErrorFault::Other,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * True when the fault is classified as client-side.
     */
    public function isClientFault(): bool
    {
        return $this->fault === ErrorFault::Client;
    }

    /**
     * True when the fault is classified as server-side.
     */
    public function isServerFault(): bool
    {
        return $this->fault === ErrorFault::Server;
    }
}
