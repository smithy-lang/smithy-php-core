<?php

/*
 * Copyright Amazon.com, Inc. or its affiliates. All Rights Reserved.
 * SPDX-License-Identifier: Apache-2.0
 */

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Timestamp serialization formats.
 *
 * Used as a codegen-time hint to serializers so they can skip runtime format lookup from schema
 * traits/extensions.
 *
 * Cases are ordered from the most frequently used wire format to the
 * least: epoch seconds, ISO 8601 date-time, then RFC 7231 HTTP-date.
 */
enum TimestampFormat: int
{
    /** Raw epoch seconds (int or float). */
    case EpochSeconds = 0;

    /** ISO 8601 date-time: Y-m-d\TH:i:s\Z */
    case DateTime = 1;

    /** RFC 7231 HTTP-date: D, d M Y H:i:s \G\M\T */
    case HttpDate = 2;

    /**
     * gmdate format string for {@see DateTime}.
     */
    public const FMT_DATE_TIME = 'Y-m-d\TH:i:s\Z';

    /**
     * gmdate format string for {@see HttpDate}.
     */
    public const FMT_HTTP_DATE = 'D, d M Y H:i:s \G\M\T';

    /**
     * Maximum day-epoch memo entries per format before the memo resets.
     */
    private const DAY_MEMO_LIMIT = 4096;

    /**
     * Format `$value` (epoch seconds) into the wire string for this format.
     *
     * The {@see EpochSeconds} arm is first: a Smithy timestamp member typed as `int` skips
     * `\is_float()` / `\sprintf()` and falls through to `(string)`.
     *
     * @param float $value Epoch seconds. Integer values pass through as-is.
     *
     * @return string Wire-format representation of `$value` in this format.
     */
    public function format(float $value): string
    {
        return match ($this->value) {
            0 => (string) ($value + 0),
            1 => \gmdate(self::FMT_DATE_TIME, (int) $value),
            2 => \gmdate(self::FMT_HTTP_DATE, (int) $value),
        };
    }

    /**
     * Parse a wire-format timestamp string into epoch seconds.
     *
     * @param string $value Canonical wire string as produced by an upstream Smithy service in this
     *               format.
     *
     * @return float Epoch seconds. Integer values are returned via PHP's int→float widening,
     *               fractional-second variants keep their fractional component.
     * @throws \InvalidArgumentException When the value cannot be parsed in this format.
     */
    public function parse(string $value): float
    {
        switch ($this->value) {
            case 0:
                if (!\is_numeric($value)) {
                    throw new \InvalidArgumentException(
                        "Invalid {$this->name} timestamp '{$value}'."
                    );
                }

                return (float) $value;

            case 1:
                $len = \strlen($value);
                // Canonical 20-byte UTC form: "2024-01-15T10:30:00Z".
                if ($len === 20) {
                    static $isoDays = [];
                    $key = \substr($value, 0, 10);
                    $day = $isoDays[$key] ?? null;
                    if ($day === null) {
                        $month = self::monthStartIso()[\substr($value, 0, 7)]
                            ?? null;
                        if ($month === null) {
                            return self::parseWithDateTime($value, $this);
                        }

                        if (\count($isoDays) >= self::DAY_MEMO_LIMIT) {
                            $isoDays = [];
                        }

                        $day = $isoDays[$key] = $month
                            + ((int) \substr($value, 8, 2) - 1) * 86400;
                    }

                    return $day
                        + (int) \substr($value, 11, 2) * 3600
                        + (int) \substr($value, 14, 2) * 60
                        + (int) \substr($value, 17, 2);
                }
                // Fractional seconds with Z: "2024-01-15T10:30:00.123Z"
                if ($len > 0 && $value[$len - 1] === 'Z') {
                    $dotPos = \strpos($value, '.', 17);
                    $month = $dotPos === false
                        ? null
                        : (self::monthStartIso()[\substr($value, 0, 7)]
                            ?? null);
                    if ($month !== null) {
                        return $month
                            + ((int) \substr($value, 8, 2) - 1) * 86400
                            + (int) \substr($value, 11, 2) * 3600
                            + (int) \substr($value, 14, 2) * 60
                            + (int) \substr($value, 17, 2)
                            + (float) (
                                '0.'
                                . \substr(
                                    $value,
                                    $dotPos + 1,
                                    $len - $dotPos - 2
                                )
                            );
                    }
                }
                // Offset variants and out-of-range dates: the date_create
                // fallback preserves fractional seconds across the full range.
                return self::parseWithDateTime($value, $this);

            case 2:
                // "Thu, 15 Jan 2024 10:30:00 GMT". Day-epoch memo keyed on
                // the "15 Jan 2024" slice.
                static $hdDays = [];
                $key = \substr($value, 5, 11);
                $day = $hdDays[$key] ?? null;
                if ($day === null) {
                    $month = self::monthStartHd()[\substr($value, 8, 8)]
                        ?? null;
                    if ($month === null) {
                        return self::parseWithDateTime($value, $this);
                    }

                    if (\count($hdDays) >= self::DAY_MEMO_LIMIT) {
                        $hdDays = [];
                    }

                    $day = $hdDays[$key] = $month
                        + ((int) \substr($value, 5, 2) - 1) * 86400;
                }

                return $day
                    + (int) \substr($value, 17, 2) * 3600
                    + (int) \substr($value, 20, 2) * 60
                    + (int) \substr($value, 23, 2);
        }
    }

    /**
     * Parse a timestamp string outside the fast-path month table and throw on failure.
     *
     * Uses `date_create_immutable()` so fractional seconds survive for dates outside the
     * 1970-2199 range (the fast paths cover in-range fractional values). `getTimestamp()` gives
     * whole seconds across the full range, and `format('u')` adds the microsecond fraction back.
     * `format('U.u')` is not used: for pre-1970 dates `U` rounds down while `u` is always
     * positive, so it would yield e.g. `-315532800.5` instead of `-315532799.5`.
     *
     * @param string $value Wire timestamp string.
     * @param self $format Expected timestamp format.
     *
     * @return float Parsed epoch seconds, including any fractional component.
     * @throws \InvalidArgumentException When the value cannot be parsed in this format.
     */
    private static function parseWithDateTime(
        string $value,
        self $format
    ): float
    {
        // date_create_immutable('') returns the current time rather than
        // false, so an empty value would silently parse to "now". Reject it
        // up front and let the false path throw.
        $parsed = $value === '' ? false : \date_create_immutable($value);
        if ($parsed !== false) {
            return $parsed->getTimestamp() + (int) $parsed->format('u') / 1e6;
        }

        throw new \InvalidArgumentException(
            "Invalid {$format->name} timestamp '{$value}'."
        );
    }

    /**
     * Return the "YYYY-MM" → epoch-seconds table for ISO date-time parsing.
     *
     * Epoch seconds at the start of each month, keyed "YYYY-MM" (1970-2199). Built once per process
     * (~2.8k entries, opcache-free static). Codegen could emit these as a const instead to share
     * via opcache.
     *
     * @return array<string, int> Map of "YYYY-MM" → epoch seconds at UTC midnight on the first
     *                            of that month.
     */
    private static function monthStartIso(): array
    {
        static $t = null;

        return $t ??= self::buildMonthTable('%04d-%02d');
    }

    /**
     * Return the "Mon YYYY" → epoch-seconds table for http-date parsing.
     *
     * Same data as {@see monthStartIso} keyed by month abbreviation and year (e.g. "Jan 2024") to
     * match the RFC 7231 wire form.
     *
     * @return array<string, int> Map of "Mon YYYY" → epoch seconds at UTC midnight on the first
     *                            of that month.
     */
    private static function monthStartHd(): array
    {
        static $t = null;
        if ($t === null) {
            $t = [];
            $names = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
            ];
            foreach (self::buildMonthTable('%04d %02d') as $k => $secs) {
                $month = $names[(int) \substr($k, 5, 2)];
                $key = $month . ' ' . \substr($k, 0, 4);
                $t[$key] = $secs;
            }
        }

        return $t;
    }

    /**
     * Build a table of month-start epoch seconds keyed by a caller-chosen `sprintf` format applied
     * to `(year, month)`.
     *
     * @param string $keyFmt `sprintf` format string that will be called with `(int $year, int
     *               $month)` and used as the table key.
     *
     * @return array<string, int> Map of formatted key → epoch seconds at UTC midnight on the
     *                            first of that month.
     */
    private static function buildMonthTable(string $keyFmt): array
    {
        $t = [];
        for ($y = 1970; $y <= 2199; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $yy = $y - (int) ($m <= 2);
                $era = \intdiv($yy, 400);
                $yoe = $yy - $era * 400;
                $doy = \intdiv(153 * ($m + ($m > 2 ? -3 : 9)) + 2, 5);
                $doe = $yoe * 365
                    + \intdiv($yoe, 4) - \intdiv($yoe, 100) + $doy;
                $t[\sprintf($keyFmt, $y, $m)]
                    = ($era * 146097 + $doe - 719468) * 86400;
            }
        }

        return $t;
    }

    /**
     * Resolve a Smithy `@timestampFormat` trait string to its enum case.
     *
     * @param string|null $trait Trait value from the model, or null when no `@timestampFormat` is
     *                    set.
     *
     * @return self|null Matching enum case, or null when `$trait` is null or names an unknown
     *                   format (caller decides the default).
     */
    public static function fromTraitString(?string $trait): ?self
    {
        return match ($trait) {
            'epoch-seconds' => self::EpochSeconds,
            'date-time' => self::DateTime,
            'http-date' => self::HttpDate,
            default => null,
        };
    }
}
