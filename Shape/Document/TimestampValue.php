<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

/**
 * Marker wrapper that tags a numeric epoch as a Smithy timestamp for
 * {@see AbstractDocument::fromValue()}.
 *
 * A bare PHP int builds an {@see IntegerDocument} and a bare float builds a {@see DoubleDocument}.
 * Wrap the value in a `TimestampValue` to route it to {@see TimestampDocument} instead.
 */
final readonly class TimestampValue
{
    /**
     * Build a timestamp marker wrapper.
     *
     * @param float $epochSeconds Timestamp payload in epoch seconds.
     */
    public function __construct(public float $epochSeconds) {}
}
