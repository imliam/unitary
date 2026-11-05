<?php

declare(strict_types=1);

namespace Unitary\Support;

use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use InvalidArgumentException;

/**
 * Evaluates the small exact expressions used in unit definitions,
 * e.g. "0.45359237 * 9.80665 / 0.3048", "pi / 180" or "10^-3".
 * Only multiplication, division and integer powers are supported.
 *
 * @internal
 */
final class Exact
{
    /** π to 64 decimal places; good enough for any real-world conversion. */
    public const PI = '3.1415926535897932384626433832795028841971693993751058209749445923';

    public static function of(BigNumber|int|string $value): BigRational
    {
        if (! is_string($value)) {
            return BigRational::of($value);
        }

        $parts = preg_split('/\s*([*\/])\s*/', trim($value), -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || $parts === ['']) {
            throw new InvalidArgumentException("Invalid expression \"{$value}\"");
        }

        $result = self::term($parts[0]);

        for ($i = 1, $count = count($parts); $i < $count; $i += 2) {
            $operand = self::term($parts[$i + 1] ?? throw new InvalidArgumentException("Invalid expression \"{$value}\""));
            $result = $parts[$i] === '*' ? $result->multipliedBy($operand) : $result->dividedBy($operand);
        }

        return $result;
    }

    public static function power(BigRational $base, int $exponent): BigRational
    {
        $result = $base->power(abs($exponent));

        return $exponent < 0 ? $result->reciprocal() : $result;
    }

    private static function term(string $term): BigRational
    {
        if (str_contains($term, '^')) {
            [$base, $exponent] = explode('^', $term, 2);

            return self::power(self::term($base), (int) $exponent);
        }

        return $term === 'pi' ? BigRational::of(self::PI) : BigRational::of($term);
    }
}
