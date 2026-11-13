<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Transport;

/**
 * Identifies the request and response types shared by a protocol and transport.
 */
interface MessageExchangeInterface
{
    /**
     * Return the transport request class.
     *
     * @return class-string<TransportRequestInterface>
     */
    public function getRequestType(): string;

    /**
     * Return the transport response class.
     *
     * @return class-string<TransportResponseInterface>
     */
    public function getResponseType(): string;

    /**
     * Determine whether another exchange uses the same message types.
     */
    public function isCompatibleWith(self $exchange): bool;
}
