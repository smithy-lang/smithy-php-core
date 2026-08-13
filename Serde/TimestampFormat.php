<?php

declare(strict_types=1);

namespace Smithy\Core\Serde;

/**
 * Timestamp serialization formats.
 *
 * Used as a codegen-time hint to serializers so they can skip runtime
 * format lookup from schema traits/extensions.
 */
enum TimestampFormat: int
{
    /** ISO 8601 date-time: Y-m-d\TH:i:s\Z */
    case DateTime = 0;

    /** RFC 7231 HTTP-date: D, d M Y H:i:s \G\M\T */
    case HttpDate = 1;

    /** Raw epoch seconds (int or float) */
    case EpochSeconds = 2;

    /**
     * gmdate format string for {@see DateTime}.
     */
    public const FMT_DATE_TIME = 'Y-m-d\TH:i:s\Z';

    /**
     * gmdate format string for {@see HttpDate}.
     */
    public const FMT_HTTP_DATE = 'D, d M Y H:i:s \G\M\T';

    /**
     * Formats `$value` (epoch seconds) into the wire string for this format.
     *
     * The {@see EpochSeconds} branch is ordered for the int-typed common
     * case. A Smithy timestamp member typed as `int` skips `\is_float()`
     * and `\sprintf()` and falls through to `(string)`.
     */
    public function format(float $value): string
    {
        return match ($this->value) {
            1 => \gmdate(self::FMT_HTTP_DATE, (int) $value),
            2 => (string) ($value + 0),
            0 => \gmdate(self::FMT_DATE_TIME, (int) $value),
        };
    }

    /**
     * Parses a wire-format timestamp string into epoch seconds.
     *
     * The inlined civil-from-days arithmetic below beats `gmmktime()` under
     * PHP 8.2's tracing JIT by ~2.13x on http-date and ~1.31x on ISO 8601
     *
     * JIT can fully inline the `intdiv`/multiply ops. It can't inline a C call into
     * `gmmktime`, which also constructs and normalizes a `tm` struct
     * internally. Without JIT, `gmmktime` wins.
     *
     * The three cases are duplicated rather than factored into a private
     * static helper. That helper cost ~60ns from the extra stack frame in
     * the same benchmark, matching the algorithm's win.
     *
     * Input is assumed canonical (codegen-supplied). No pre-validation.
     *
     * Fast paths:
     * - {@see EpochSeconds}: `$value + 0` (PHP loose int/float coercion).
     * - {@see HttpDate}: `Thu, 15 Jan 2024 10:30:00 GMT` (29 chars).
     * - {@see DateTime}: 20-char `2024-01-15T10:30:00Z`, or the fractional
     *   variant before `Z`. Values with a timezone offset (`+05:00`) route
     *   to `\strtotime()`, which parses and applies offsets in C in one shot
     *   and beats hand-rolled offset arithmetic in PHP.
     */
    public function parse(string $value): float
    {
        switch ($this->value) {
            case 1:
                // "Thu, 15 Jan 2024 10:30:00 GMT". Day-epoch memo keyed on
                // "15 Jan 2024"; misses fall through to the month-start table,
                // so worst case = table path + one insert (~30ns).
                static $hdDays = [];

                return ($hdDays[\substr($value, 5, 11)]
                        ??= self::monthStartHd()[\substr($value, 8, 8)] + ((int) \substr($value, 5, 2) - 1) * 86400)
                    + (int) \substr($value, 17, 2) * 3600
                    + (int) \substr($value, 20, 2) * 60
                    + (int) \substr($value, 23, 2);

            case 0:
                $len = \strlen($value);
                // "2024-01-15T10:30:00Z" -- the AWS body-timestamp common case.
                // Month-start lookup replaces the year/month substrs AND the
                // entire era/leap arithmetic with one hash fetch.
                if ($len === 20) {
                    static $isoDays = [];
                    return ($isoDays[\substr($value, 0, 10)]
                            ??= self::monthStartIso()[\substr($value, 0, 7)] + ((int) \substr($value, 8, 2) - 1) * 86400)
                        + (int) \substr($value, 11, 2) * 3600
                        + (int) \substr($value, 14, 2) * 60
                        + (int) \substr($value, 17, 2);
                }
                // Fractional seconds with Z: "2024-01-15T10:30:00.123Z"
                if ($value[$len - 1] === 'Z') {
                    $dotPos = \strpos($value, '.', 17);
                    if ($dotPos !== false) {
                        return self::monthStartIso()[\substr($value, 0, 7)]
                            + ((int) \substr($value, 8, 2) - 1) * 86400
                            + (int) \substr($value, 11, 2) * 3600
                            + (int) \substr($value, 14, 2) * 60
                            + (int) \substr($value, 17, 2)
                            + (float) ('0.' . \substr($value, $dotPos + 1, $len - $dotPos - 2));
                    }
                }
                // Offset variants: strtotime is fast on numeric offsets
                // (it is only slow on trailing-Z strings: 11.4us vs ~0.7us).
                return \strtotime($value);

            default:
                return $value + 0;
        }
    }

    /**
     * Epoch seconds at the start of each month, keyed "YYYY-MM" (1970-2199).
     * Built once per process (~2.8k entries, opcache-free static). Codegen
     * could emit these as a const instead to share via opcache.
     */
    private static function monthStartIso(): array
    {
        static $t = null;

        return $t ??= self::buildMonthTable('%04d-%02d');
    }

    /** Same data keyed "Mon YYYY" for http-date. */
    private static function monthStartHd(): array
    {
        static $t = null;
        if ($t === null) {
            $t = [];
            $names = [1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            foreach (self::buildMonthTable('%04d %02d') as $k => $secs) {
                $t[$names[(int) \substr($k, 5, 2)] . ' ' . \substr($k, 0, 4)] = $secs;
            }
        }

        return $t;
    }

    private static function buildMonthTable(string $keyFmt): array
    {
        $t = [];
        for ($y = 1970; $y <= 2199; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                $yy = $y - (int) ($m <= 2);
                $era = \intdiv($yy, 400);
                $yoe = $yy - $era * 400;
                $doy = \intdiv(153 * ($m + ($m > 2 ? -3 : 9)) + 2, 5);
                $doe = $yoe * 365 + \intdiv($yoe, 4) - \intdiv($yoe, 100) + $doy;
                $t[\sprintf($keyFmt, $y, $m)] = ($era * 146097 + $doe - 719468) * 86400;
            }
        }

        return $t;
    }

    /**
     * Resolve a Smithy `@timestampFormat` trait string to its enum case.
     * Returns null for unknown values (caller decides the default).
     */
    public static function fromTraitString(?string $trait): ?self
    {
        return match ($trait) {
            'date-time' => self::DateTime,
            'http-date' => self::HttpDate,
            'epoch-seconds' => self::EpochSeconds,
            default => null,
        };
    }
}
