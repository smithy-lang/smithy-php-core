<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

use Smithy\Core\Serde\TimestampFormat;

/**
 * Wire-format settings that decide how a document's values map onto modeled shape members.
 */
interface DocumentSettingsInterface
{
    /**
     * Return true when members are read by their `jsonName` trait.
     */
    public function usesJsonName(): bool;

    /**
     * Return true when a member's `timestampFormat` trait selects the string timestamp format.
     */
    public function usesTimestampFormatTrait(): bool;

    /**
     * Return the string timestamp format used when no trait applies.
     */
    public function getDefaultTimestampFormat(): TimestampFormat;
}
