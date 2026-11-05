<?php

declare(strict_types=1);

namespace Unitary\Parsing;

use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Unitary\Exceptions\ParseException;

/**
 * Parses human-written numbers into exact rationals: "1,234.5", "-3", "1.5e-9",
 * "1/3", "2 1/2", "2½", "½".
 *
 * @internal
 */
final class NumberParser
{
    private const VULGAR_FRACTIONS = [
        '½' => '1/2', '⅓' => '1/3', '⅔' => '2/3', '¼' => '1/4', '¾' => '3/4',
        '⅕' => '1/5', '⅖' => '2/5', '⅗' => '3/5', '⅘' => '4/5', '⅙' => '1/6',
        '⅚' => '5/6', '⅐' => '1/7', '⅛' => '1/8', '⅜' => '3/8', '⅝' => '5/8',
        '⅞' => '7/8', '⅑' => '1/9', '⅒' => '1/10',
    ];

    /**
     * A regex fragment (no delimiters, uses named groups) matching an unsigned number.
     */
    public const PATTERN = '(?:'
        .'(?<whole>\d+)(?:\s+|-)(?<fn>\d+)\/(?<fd>\d+)(?![\d.])'                // mixed: 2 1/2
        .'|(?<n>\d+)\/(?<d>\d+)(?![\d.])'                                       // fraction: 1/3
        .'|(?<dec>(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?|\.\d+)(?:[eE](?<exp>[-+]?\d+))?(?<vulgar>[½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅐⅛⅜⅝⅞⅑⅒])?'
        .'|(?<lone>[½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅐⅛⅜⅝⅞⅑⅒])'
        .')';

    public static function parse(BigNumber|int|float|string $value): BigRational
    {
        return self::tryParse($value) ?? throw ParseException::cannotParse((string) $value, 'number');
    }

    public static function tryParse(BigNumber|int|float|string $value): ?BigRational
    {
        if ($value instanceof BigNumber) {
            return $value->toBigRational();
        }

        if (is_int($value)) {
            return BigRational::of($value);
        }

        if (is_float($value)) {
            // Go via the shortest round-tripping repr so 0.1 is 1/10, not 0.1000000000000000055...
            return is_finite($value) ? BigRational::of(self::floatToString($value)) : null;
        }

        $value = str_replace(['−', '_', "\u{00A0}"], ['-', '', ''], trim($value));

        if (! preg_match('/^(?<sign>[-+])?\s*'.self::PATTERN.'$/u', $value, $matches)) {
            return null;
        }

        return self::fromMatches($matches);
    }

    /**
     * @param  array<array-key, string>  $matches  The match groups from PATTERN, optionally with "sign".
     */
    public static function fromMatches(array $matches): BigRational
    {
        foreach (['fd', 'd'] as $denominator) {
            if (($matches[$denominator] ?? '') !== '' && ltrim($matches[$denominator], '0') === '') {
                throw ParseException::cannotParse($matches[0] ?? '', 'number', 'division by zero');
            }
        }

        $number = match (true) {
            ($matches['whole'] ?? '') !== '' => BigRational::of($matches['whole'])->plus(BigRational::ofFraction($matches['fn'] ?? '0', $matches['fd'] ?? '1')),
            ($matches['n'] ?? '') !== '' => BigRational::ofFraction($matches['n'], $matches['d'] ?? '1'),
            ($matches['lone'] ?? '') !== '' => BigRational::of(self::VULGAR_FRACTIONS[$matches['lone']]),
            default => self::decimal($matches),
        };

        return ($matches['sign'] ?? '') === '-' ? $number->negated() : $number;
    }

    /**
     * @param  array<array-key, string>  $matches
     */
    private static function decimal(array $matches): BigRational
    {
        $digits = str_replace(',', '', $matches['dec'] ?? '0');
        $exponent = ($matches['exp'] ?? '') !== '' ? 'e'.$matches['exp'] : '';
        $number = BigRational::of($digits.$exponent);

        if (($matches['vulgar'] ?? '') !== '') {
            $number = $number->plus(BigRational::of(self::VULGAR_FRACTIONS[$matches['vulgar']]));
        }

        return $number;
    }

    /**
     * Significant figures in a written number: "2.50" → 3, "0.025" → 2, "1.00e3" → 3.
     * Trailing zeros of a whole number aren't counted ("2500" → 2). Fractions are exact (null).
     */
    public static function significantFigures(string $value): ?int
    {
        $value = str_replace([',', '_', '−'], ['', '', '-'], trim($value));

        if (! preg_match('/^[-+]?(?<int>\d*)(?:\.(?<frac>\d*))?(?:[eE][-+]?\d+)?$/', $value, $matches)) {
            return null;
        }

        $digits = $matches['int'].($matches['frac'] ?? '');

        if ($digits === '') {
            return null;
        }

        $digits = ltrim($digits, '0');

        if (! str_contains($value, '.')) {
            $digits = rtrim($digits, '0');
        }

        return max(1, strlen($digits));
    }

    private static function floatToString(float $value): string
    {
        return strtolower(var_export($value, true));
    }
}
