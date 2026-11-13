<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Endpoint;

/**
 * A resolved endpoint for an operation.
 *
 * Produced by an {@see EndpointResolverInterface} for each request attempt.
 * The protocol layer combines `url` with the operation-relative URI created
 * during serialization.
 *
 * Endpoint auth schemes provide deprecated compatibility overrides for the
 * resolver-selected scheme. Headers are merged into the request before
 * signing.
 */
final class Endpoint
{
    /**
     * Create a resolved endpoint.
     *
     * @param list<EndpointAuthScheme> $authSchemes
     * @param array<string, string> $headers
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $url,
        public readonly array $authSchemes = [],
        public readonly array $headers = [],
        public readonly array $properties = []
    ) {}

    /**
     * Return an endpoint property, or null when it is not present.
     */
    public function getProperty(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Return whether an endpoint property is present.
     */
    public function hasProperty(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }
}
