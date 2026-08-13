<?php

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
