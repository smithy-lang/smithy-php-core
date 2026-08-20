<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

/**
 * Codec-facing deserialization entry point for generated struct classes.
 *
 * The runtime deserializer builds a positional `$members` array by hydrating each slot to the
 * type the matching constructor parameter accepts (raw scalars, enum instances, nested struct
 * instances, lists, maps), then calls {@see fromMembers()}.
 *
 * The trait exists so codegen does not emit an identical one-line method on every generated
 * struct. The implementation is universally `return new static(...$members)` because the
 * deserializer pre-hydrates every slot to the target type.
 */
trait FromMembersTrait
{
    use WithMemberValueTrait;

    /**
     * Build an instance from pre-hydrated positional member slots.
     *
     * @internal Called by the runtime deserializer. Not part of the public API. Callers
     *           constructing an instance from application-owned data should use
     *           {@see \Smithy\Core\Shape\Utils\ArrayShapeSerializer::fromArray()} instead.
     *
     * @param array<int, mixed> $members Positional values indexed by member index. Ordering
     *              follows the codegen constructor ordering: required members first, defaulted
     *              members second, optional members last.
     *
     * @return static Hydrated shape instance.
     */
    public static function fromMembers(array $members): static
    {
        return new static(...$members);
    }
}
