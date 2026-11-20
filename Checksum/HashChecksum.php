<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Checksum;

/**
 * A {@see Checksum} backed by PHP's incremental hash API.
 */
final class HashChecksum implements Checksum
{
    private readonly \HashContext $context;

    private readonly int $digestLength;

    /**
     * @param string $hashAlgorithm a {@see \hash_algos()} identifier
     *
     * @throws \ValueError when the runtime does not provide the algorithm
     */
    public function __construct(string $hashAlgorithm)
    {
        $this->context = \hash_init($hashAlgorithm);
        $this->digestLength = \strlen(\hash($hashAlgorithm, '', true));
    }

    public function update(string $bytes): void
    {
        \hash_update($this->context, $bytes);
    }

    public function digest(): string
    {
        return \hash_final(\hash_copy($this->context), true);
    }

    public function digestLength(): int
    {
        return $this->digestLength;
    }
}
