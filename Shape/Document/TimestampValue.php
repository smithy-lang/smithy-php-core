<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

/**
 * Marker wrapper that tags a numeric epoch as a Smithy timestamp for
 * {@see AbstractDocument::ofObject()}.
 *
 * A bare PHP int builds an {@see IntegerDocument} and a bare float builds a {@see DoubleDocument}.
 * Wrap the value in a `TimestampValue` to route it to {@see TimestampDocument} instead.
 */
final readonly class TimestampValue
{
    public function __construct(public float $epochSeconds) {}
}
