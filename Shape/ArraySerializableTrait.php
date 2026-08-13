<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

/**
 * Per-shape entry point for `toArray()` and `fromArray()`.
 *
 * The dispatch logic lives in {@see ArrayShapeSerializer}. This trait is the thin shim that
 * generated structures and unions `use` so callers see the same `$shape->toArray()` and
 * `Foo::fromArray($data)` API they had when the methods were emitted into every class. Centralizing
 * the body in one class keeps duplicated bytecode across generated shapes down to two one-line
 * shims.
 */
trait ArraySerializableTrait
{
    /**
     * Convert this shape into its V3-compatible associative-array form.
     *
     * @return array<string, mixed> V3-shaped payload keyed by PascalCase wire member names.
     */
    public function toArray(): array
    {
        return ArrayShapeSerializer::toArray($this);
    }

    /**
     * Build an instance of this shape from an associative array.
     *
     * @param array<string, mixed> $data V3-shaped payload keyed by PascalCase wire member names.
     *
     * @return static Hydrated shape instance.
     */
    public static function fromArray(array $data): static
    {
        /** @var static */
        return ArrayShapeSerializer::fromArray(static::schema(), $data);
    }
}
