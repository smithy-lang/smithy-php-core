<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

/**
 * Implemented by documents that were read from a wire format and remember its settings.
 *
 * {@see DocumentInterface::asShape()} reads the settings to choose member names and timestamp
 * formats. Documents built in application code return null and use the default member lookup.
 */
interface DocumentSettingsAwareInterface
{
    /**
     * Return the settings of the codec that read this document, or null for user-built documents.
     */
    public function getDocumentSettings(): ?DocumentSettingsInterface;
}
