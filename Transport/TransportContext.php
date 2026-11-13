<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Transport;

use Smithy\Core\Shape\OperationInterface;

/**
 * Operation and request options supplied to a transport attempt.
 */
final readonly class TransportContext
{
    public function __construct(
        public OperationInterface $operation,
        public ?TransportOptionsInterface $options = null
    ) {}
}
