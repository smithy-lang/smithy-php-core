<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Computes extension data for a given schema.
 *
 * Providers are invoked lazily on first access to a schema's extension slot
 * via `Schema::getExtension()`. The returned value is cached on the schema
 * (immutable after first computation).
 */
interface SchemaExtensionProviderInterface
{
    /**
     * Compute extension data for `$schema`.
     *
     * @return mixed The value to cache. May be null.
     */
    public function provide(Schema $schema): mixed;
}
