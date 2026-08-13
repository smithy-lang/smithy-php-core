<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

/**
 * Read-only {@see \ArrayAccess} implementation for typed value objects.
 *
 * Maps PascalCase wire names (e.g., `Credentials`, `AccessKeyId`) to camelCase readonly
 * properties on the implementing class through `lcfirst()`. Preserves SDK v3's
 * `$result['Credentials']` syntax while enforcing immutability.
 *
 * @implements \ArrayAccess<string, mixed>
 */
trait ReadonlyArrayAccessTrait
{
    public function offsetExists(mixed $offset): bool
    {
        if (!\is_string($offset)) {
            return false;
        }

        $prop = \lcfirst($offset);

        return isset($this->{$prop});
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!\is_string($offset)) {
            return null;
        }

        $prop = \lcfirst($offset);

        return $this->{$prop} ?? null;
    }

    /**
     * @throws \BadMethodCallException Always. The trait blocks mutation.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException(
            static::class
                . ' is immutable. Cannot set offset "' . $offset . '".'
        );
    }

    /**
     * @throws \BadMethodCallException Always. The trait blocks mutation.
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException(
            static::class
                . ' is immutable. Cannot unset offset "' . $offset . '".'
        );
    }
}
