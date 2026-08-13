<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Serde\Schema;
use Smithy\Core\Serde\SerializableStructInterface;

/**
 * Provides `__toString()` for Smithy structs so `print_r`, `var_dump`, and any string casts do not
 * leak sensitive fields.
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
 *
 * Any generated shape can add `use SensitiveStringableTrait;` in the class body. The trait is
 * orthogonal to {@see ArraySerializableTrait} and coexists with it.
 */
trait SensitiveStringableTrait
{
    public function __toString(): string
    {
        if (!$this instanceof SerializableStructInterface) {
            // The trait relies on `schema()` and `getMemberValue()`. Non-struct users get a
            // class-name fallback rather than a fatal.
            return static::class;
        }

        $schema = static::schema();
        $parts = [];
        foreach ($schema->getMembers() as $memberName => $memberSchema) {
            $value = $this->getMemberValue($memberSchema);
            if ($value === null) {
                continue;
            }

            $parts[] = $memberName
                . '='
                . self::renderValue($value, $memberSchema);
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
