<?php

declare(strict_types=1);

namespace Smithy\Core\Shape\Document;

/**
 * Marker wrapper that tags raw bytes as a Smithy blob for
 * {@see AbstractDocument::fromValue()}.
 *
 * A bare PHP string builds a {@see StringDocument}. Wrap the bytes in a `BlobValue` to route them
 * to {@see BlobDocument} instead.
 */
final readonly class BlobValue
{
    /**
     * Build a blob marker wrapper.
     *
     * @param string $bytes Raw blob bytes.
     */
    public function __construct(public string $bytes) {}
}
