<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Retry;

/**
 * Whether it is safe to retry an error.
 */
enum RetrySafety
{
    /** Retrying is allowed. */
    case Yes;

    /** Retrying must not happen. */
    case No;

    /** Not enough information to decide. Treated as not retryable. */
    case Maybe;
}
