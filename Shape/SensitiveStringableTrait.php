<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;

/**
 * Provides safe string and debug representations for modeled values.
 *
 * The trait consults the shape's own schema. A struct redacts every member whose schema carries
 * `Schema::T_SENSITIVE`, either directly on the member (a member with `@sensitive`) or via
 * `@sensitive` on its target shape (e.g., a `SecretString` target). Codegen propagates the
 * target-level trait onto the member schema at emit time, so both cases land at the same runtime
 * check.
 *
 * The output is deterministic, keyed by member name, and includes only populated members.
 * Sensitive members render as `"***REDACTED***"` regardless of value. Non-sensitive struct or
 * union members recurse (their own `__toString()` handles redaction).
 */
trait SensitiveStringableTrait
{
    /**
     * Return a schema-aware representation for PHP debugging functions.
     *
     * @return array<string, mixed> Redacted debug representation.
     */
    public function __debugInfo(): array
    {
        return SensitiveDataRedactor::debugInfo($this);
    }

    /**
     * Return a schema-aware representation for JSON serialization.
     *
     * @return array<string, mixed> Redacted JSON representation.
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * Return a schema-aware representation for PHP serialization.
     *
     * @return array<string, mixed> Redacted serialized representation.
     */
    public function __serialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * Reject restoration from a redacted serialized representation.
     *
     * @param array<string, mixed> $_data
     */
    public function __unserialize(array $_data): void
    {
        throw new \LogicException(
            'Sensitive modeled values cannot be restored from PHP '
            . 'serialization.'
        );
    }

    /**
     * Render the struct in a redacted, deterministic string form.
     *
     * @return string Redacted string form of the struct.
     */
    public function __toString(): string
    {
        if (!$this instanceof SerializableStructInterface) {
            // The trait relies on `schema()` and `getMemberValue()`. Non-struct users get a
            // class-name fallback rather than a fatal.
            return static::class;
        }

        $schema = $this->getSchema();
        $redactAll = $schema->hasTrait(Schema::T_SENSITIVE);
        $parts = [];
        foreach ($schema->getMembers() as $memberName => $memberSchema) {
            $value = $this->getMemberValue($memberSchema);
            if ($value === null) {
                continue;
            }

            $parts[] = $memberName
                . '='
                . ($redactAll
                    ? '"***REDACTED***"'
                    : self::renderValue($value, $memberSchema));
        }

        $class = self::shortClass();

        return $class . '(' . \implode(', ', $parts) . ')';
    }

    /**
     * Render a single member value, redacting when the member's schema carries `@sensitive` and
     * delegating to nested `__toString()` for struct or union values.
     *
     * @param mixed $value Runtime value of the member.
     * @param Schema $memberSchema Schema describing the member.
     *
     * @return string Rendered form of the value.
     */
    private static function renderValue(mixed $value, Schema $memberSchema): string
    {
        if ($memberSchema->hasTrait(Schema::T_SENSITIVE)) {
            return '"***REDACTED***"';
        }

        // Dispatch on `gettype()` for the fast path. `gettype()` returns "double" for floats, so
        // integer and double share one arm. The array arm surfaces size only. Recursing into
        // list/map items would open a redaction hole (a sensitive list-target's item schema
        // needs separate resolution) and is deferred to a later pass. Object dispatch narrows
        // through an inner `match(true)` since `gettype()` cannot discriminate object subtypes.
        return match (\gettype($value)) {
            'string' => '"' . $value . '"',
            'boolean' => $value ? 'true' : 'false',
            'integer', 'double' => (string) $value,
            'array' => '['
                . \count($value)
                . ' item' . (\count($value) === 1 ? '' : 's')
                . ']',
            'object' => match (true) {
                $value instanceof EnumInterface => (string) $value->getValue(),
                // Stringable delegates to the target's own `__toString()`, which for generated
                // structs using this trait redacts recursively.
                $value instanceof \Stringable => (string) $value,
                // Fallback: render the class name so logging stays safe without triggering type
                // errors.
                default => '<' . $value::class . '>'
            },
            default => \var_export($value, true)
        };
    }

    /**
     * Return the local-name portion of the class ID for the header of the string form.
     *
     * @return string Class basename, or the fully-qualified class name when no namespace is
     *                present.
     */
    private static function shortClass(): string
    {
        $fqcn = static::class;
        $lastSlash = \strrpos($fqcn, '\\');

        return $lastSlash === false ? $fqcn : \substr($fqcn, $lastSlash + 1);
    }
}
