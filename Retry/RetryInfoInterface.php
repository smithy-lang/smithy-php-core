<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Retry;

/**
 * Describe how a retry strategy should treat a failure.
 *
 * An exception, or any exception in its previous-chain, can implement this
 * interface to tell {@see \Smithy\Client\Retry\StandardRetryStrategy} whether
 * the failure is retryable, whether it is a throttle, and how long the server
 * asked the caller to wait.
 *
 * The contract lives in `smithy/core` so error shapes (which depend only on
 * core) can carry their own retry classification without depending on the
 * client package that implements the strategy.
 */
interface RetryInfoInterface
{
    /**
     * Return whether it is safe to retry the failure.
     */
    public function getRetrySafety(): RetrySafety;

    /**
     * Return true when the failure is a throttling error.
     */
    public function isThrottle(): bool;

    /**
     * Return the server-suggested wait in seconds, or null for no hint.
     */
    public function getRetryAfter(): ?float;
}
