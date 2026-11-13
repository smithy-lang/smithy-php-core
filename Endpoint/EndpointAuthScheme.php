<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Endpoint;

/**
 * Carry an auth scheme override attached to a resolved {@see Endpoint}.
 *
 * Endpoint rulesets can augment or replace the auth scheme selected before
 * endpoint resolution. This is deprecated compatibility behavior; new
 * services should select schemes through the client's auth scheme
 * resolver. The scheme ID is the canonical Smithy shape ID of the auth
 * scheme, such as `aws.auth#sigv4`.
 */
final class EndpointAuthScheme
{
    /**
     * Create an endpoint auth-scheme override.
     *
     * @param array<string, mixed> $properties Signer property overrides.
     */
    public function __construct(
        public readonly string $schemeId,
        public readonly array $properties = []
    ) {}
}
