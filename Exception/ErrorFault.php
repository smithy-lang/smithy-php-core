<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Exception;

/**
 * Fault classification for API errors.
 */
enum ErrorFault: string
{
    case Client = 'client';
    case Server = 'server';
    case Other = 'other';
}
