<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape\Exception;

/**
 * Thrown when a runtime value cannot satisfy a modeled shape.
 */
final class InvalidShapeValueException extends ShapeException {}
