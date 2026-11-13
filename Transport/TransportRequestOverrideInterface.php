<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Transport;

/**
 * Apply transport-specific behavior to one client request.
 *
 * Implementations own the request mutation and option types for their
 * message exchange. The generic client pipeline does not inspect them.
 */
interface TransportRequestOverrideInterface
{
    /**
     * Resolve transport options for this request.
     */
    public function resolveOptions(
        TransportInterface $transport
    ): ?TransportOptionsInterface;

    /**
     * Apply message-level overrides to a serialized request.
     *
     * The pipeline applies overrides before interceptor processing and
     * reapplies them after endpoint binding. Implementations must therefore
     * be safe to apply more than once to the same logical request.
     */
    public function applyToRequest(
        TransportRequestInterface $request
    ): TransportRequestInterface;
}
