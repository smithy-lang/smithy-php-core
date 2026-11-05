<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;

/**
 * Provide schema-indexed copying for positional structure constructors.
 */
trait WithMemberValueTrait
{
    /**
     * Return a copy with one positional member replaced.
     *
     * @param Schema $member Member schema to replace.
     * @param mixed $value Replacement value.
     *
     * @return static Copied shape instance.
     */
    public function withMemberValue(Schema $member, mixed $value): static
    {
        $members = [];
        foreach ($this->getSchema()->getMembersByIndex() as $index => $schema) {
            $members[$index] = $this->getMemberValue($schema);
        }
        $members[$member->memberIndex] = $value;
        \ksort($members);

        return new static(...$members);
    }
}
