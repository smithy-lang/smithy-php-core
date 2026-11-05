<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

/**
 * Thrown when a document coercion call requests a data-model type the underlying document cannot
 * satisfy (`asMap()` on a {@see BlobDocument}, for example).
 */
final class IncompatibleDocumentTypeException extends \RuntimeException {}
