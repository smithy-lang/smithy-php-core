<?php

declare(strict_types=1);

namespace Smithy\Core\Shape;

/**
 * Runtime value type for Smithy `bigInteger` shapes.
 *
 * Holds the wire representation verbatim as a digit string. The constructor validates the input
 * against the integer subset of the JSON number grammar (RFC 8259 §6). Leading zeros are accepted
 * at construction (strict JSON forbids them, but the JSON encoder normalizes on wire emission).
 *
 * Arithmetic is opt-in. Pass `$value` to `bcmath`, `gmp`, or `brick/math` at the application layer.
 */
final readonly class BigInteger
{
    /**
     * Optional sign followed by one or more decimal digits. The integer subset of RFC 8259 §6 (no
     * fractional part, no exponent).
     */
    private const VALUE_PATTERN = '/^-?\d+$/';

    /**
     * @param string $value Wire representation of the integer. Must be an optional minus sign
     *               followed by one or more decimal digits.
     *
     * @return void
     * @throws \InvalidArgumentException When `$value` does not match the integer subset of the JSON
     *                                   number grammar.
     */
    public function __construct(public string $value)
    {
        if (!\preg_match(self::VALUE_PATTERN, $value)) {
            throw new \InvalidArgumentException(
                "Invalid BigInteger value '{$value}': "
                    . 'expected an optional minus sign followed by digits.',
            );
        }
    }
}
