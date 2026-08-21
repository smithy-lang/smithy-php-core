<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

use Smithy\Core\Shape\Exception\InvalidShapeValueException;

/**
 * Runtime value type for Smithy `bigDecimal` shapes.
 *
 * Holds the wire representation verbatim as a numeric string. The constructor validates the input
 * against the JSON number grammar (RFC 8259 §6), so any reachable `BigDecimal` is a valid number
 * token by construction.
 *
 * Arithmetic is opt-in. Pass `$value` to `bcmath`, `gmp`, or `brick/math` at the application layer.
 */
final readonly class BigDecimal
{
    /**
     * Optional sign, integer part (either `0` or a non-zero digit followed by any digits), optional
     * fractional part, optional exponent. Mirrors the JSON number grammar from RFC 8259 §6, so
     * leading zeros on the integer part are rejected.
     */
    private const VALUE_PATTERN = '/^-?(0|[1-9]\d*)(\.\d+)?([eE][-+]?\d+)?$/';

    /**
     * @param string $value Wire representation of the decimal number. Must match the JSON number
     *               grammar (RFC 8259 §6): optional sign, integer part (either `0` or a non-zero
     *               digit followed by any digits), optional fractional part, optional exponent.
     *
     * @throws InvalidShapeValueException When `$value` does not match the JSON number grammar.
     */
    public function __construct(public string $value)
    {
        if (!\preg_match(self::VALUE_PATTERN, $value)) {
            throw new InvalidShapeValueException(
                "Invalid BigDecimal value '{$value}': "
                    . 'expected a decimal number, optionally in exponent form.'
            );
        }
    }
}
