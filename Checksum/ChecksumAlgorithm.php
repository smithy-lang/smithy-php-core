<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Checksum;

/**
 * A flexible-checksum algorithm. The case value is the algorithm identifier;
 * the PHP hash name it maps to stays private to the enum.
 */
enum ChecksumAlgorithm: string
{
    case CRC32 = 'CRC32';
    case CRC32C = 'CRC32C';
    case SHA1 = 'SHA1';
    case SHA256 = 'SHA256';
    case SHA512 = 'SHA512';
    case XXHASH64 = 'XXHASH64';
    case XXHASH3 = 'XXHASH3';
    case XXHASH128 = 'XXHASH128';

    /**
     * Resolve an algorithm by identifier, case-insensitively, or null when it
     * names no known algorithm.
     */
    public static function tryFromName(string $name): ?self
    {
        return self::tryFrom(\strtoupper($name));
    }

    /**
     * Return the algorithms the runtime can compute, in declaration order.
     *
     * @return list<self>
     */
    public static function supported(): array
    {
        return \array_values(\array_filter(
            self::cases(),
            static fn (self $algorithm): bool => $algorithm->isSupported(),
        ));
    }

    /**
     * Resolve an algorithm the runtime can compute for a request checksum, or
     * raise a clear error naming the supported algorithms.
     *
     * @throws \InvalidArgumentException when the name is unknown or the runtime
     *     cannot compute it
     */
    public static function resolveRequest(string $name): self
    {
        $algorithm = self::tryFromName($name);
        if ($algorithm !== null && $algorithm->isSupported()) {
            return $algorithm;
        }

        throw new \InvalidArgumentException(\sprintf(
            "Unsupported flexible checksum algorithm '%s'. Supported "
            . 'algorithms are: %s.',
            $name,
            \implode(', ', \array_map(
                static fn (self $supported): string => $supported->value,
                self::supported(),
            )),
        ));
    }

    /**
     * Create a fresh {@see Checksum} that computes this algorithm.
     */
    public function create(): Checksum
    {
        return new HashChecksum($this->hashName());
    }

    /**
     * Report whether the runtime's hash extension provides this algorithm.
     */
    public function isSupported(): bool
    {
        return \in_array($this->hashName(), \hash_algos(), true);
    }

    /**
     * Return the checksum length in bytes.
     */
    public function digestLength(): int
    {
        return \strlen(\hash($this->hashName(), '', true));
    }

    /**
     * Return the PHP {@see \hash_algos()} identifier for this algorithm.
     */
    private function hashName(): string
    {
        return match ($this) {
            self::CRC32 => 'crc32b',
            self::CRC32C => 'crc32c',
            self::SHA1 => 'sha1',
            self::SHA256 => 'sha256',
            self::SHA512 => 'sha512',
            self::XXHASH64 => 'xxh64',
            self::XXHASH3 => 'xxh3',
            self::XXHASH128 => 'xxh128',
        };
    }
}
