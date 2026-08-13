<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Integer-indexed key for O(1) schema extension access.
 *
 * Each key holds its own provider and a unique int ID. Schema stores extensions in a packed array
 * keyed on `$id`, so lookup is a direct offset read. Keys are singletons created lazily via each
 * provider's static `key()` method.
 */
final class SchemaExtensionKey
{
    private static int $nextId = 0;
    public readonly int $id;

    /**
     * @param SchemaExtensionProviderInterface $provider Provider that supplies the value for this
     *                                         extension slot.
     *
     * @return void
     */
    public function __construct(
        public readonly SchemaExtensionProviderInterface $provider
    )
    {
        $this->id = self::$nextId++;
    }
}
