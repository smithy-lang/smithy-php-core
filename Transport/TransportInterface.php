<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Transport;

/**
 * Sends protocol messages without constraining orchestration to HTTP.
 */
interface TransportInterface
{
    /**
     * Return the request and response types accepted by this transport.
     */
    public function getMessageExchange(): MessageExchangeInterface;

    /**
     * Return the transport's default request options, when available.
     */
    public function getDefaultOptions(): ?TransportOptionsInterface;

    /**
     * Send one request and return its response.
     */
    public function send(
        TransportRequestInterface $request,
        TransportContext $context
    ): TransportResponseInterface;

    /**
     * Prepare request state for another attempt.
     *
     * Return false when the request cannot be safely replayed.
     */
    public function prepareRequestForRetry(
        TransportRequestInterface $request,
        ?TransportResponseInterface $response,
        TransportContext $context,
        \Throwable $error
    ): bool;
}
