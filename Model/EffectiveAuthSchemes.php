<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Model;

/**
 * Resolves the ordered authentication schemes effective for an operation.
 */
final class EffectiveAuthSchemes
{
    public const NO_AUTH = 'smithy.api#noAuth';

    private const AUTH = 'smithy.api#auth';
    private const AUTH_DEFINITION = 'smithy.api#authDefinition';
    private const OPTIONAL_AUTH = 'smithy.api#optionalAuth';
    private const PRELUDE_AUTH_SCHEMES = [
        'smithy.api#httpApiKeyAuth' => true,
        'smithy.api#httpBasicAuth' => true,
        'smithy.api#httpBearerAuth' => true,
        'smithy.api#httpDigestAuth' => true
    ];

    private function __construct() {}

    /**
     * @param array<string, array<string, mixed>> $shapes
     * @param array<string, mixed> $serviceTraits
     * @param array<string, mixed> $operationTraits
     *
     * @return list<string>
     */
    public static function resolve(
        array $shapes,
        array $serviceTraits,
        array $operationTraits
    ): array
    {
        $schemeIds = self::getExplicitAuthSchemes(
            $operationTraits,
            $serviceTraits
        );
        if ($schemeIds === null) {
            $schemeIds = self::getExplicitAuthSchemes(
                $serviceTraits,
                $serviceTraits
            );
        }
        if ($schemeIds === null) {
            $schemeIds = self::findAppliedAuthSchemes(
                $shapes,
                $serviceTraits
            );
        }

        if ($schemeIds === []
            || isset($operationTraits[self::OPTIONAL_AUTH])
        ) {
            $schemeIds[] = self::NO_AUTH;
        }

        return $schemeIds;
    }

    /**
     * @param array<string, mixed> $traits
     * @param array<string, mixed> $serviceTraits
     *
     * @return list<string>|null
     */
    private static function getExplicitAuthSchemes(
        array $traits,
        array $serviceTraits
    ): ?array
    {
        if (!isset($traits[self::AUTH])) {
            return null;
        }

        $auth = $traits[self::AUTH];
        if (!\is_array($auth)) {
            return [];
        }

        $schemeIds = [];
        foreach ($auth as $schemeId) {
            if (\is_string($schemeId)
                && isset($serviceTraits[$schemeId])
            ) {
                $schemeIds[] = $schemeId;
            }
        }

        return $schemeIds;
    }

    /**
     * @param array<string, array<string, mixed>> $shapes
     * @param array<string, mixed> $serviceTraits
     *
     * @return list<string>
     */
    private static function findAppliedAuthSchemes(
        array $shapes,
        array $serviceTraits
    ): array
    {
        $schemeIds = [];
        foreach ($serviceTraits as $traitId => $_) {
            if (isset(self::PRELUDE_AUTH_SCHEMES[$traitId])) {
                $schemeIds[] = $traitId;
                continue;
            }

            $shape = $shapes[$traitId] ?? null;
            $traitTraits = \is_array($shape)
                ? ($shape['traits'] ?? [])
                : [];
            if (\is_array($traitTraits)
                && isset($traitTraits[self::AUTH_DEFINITION])
            ) {
                $schemeIds[] = $traitId;
            }
        }

        \sort($schemeIds, \SORT_STRING);

        return $schemeIds;
    }
}
