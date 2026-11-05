<?php

declare(strict_types=1);

namespace Unitary\Color\Internal;

/**
 * @internal
 */
final class Format
{
    /**
     * Rounds to the given number of decimal places and strips trailing zeros.
     */
    public static function number(float $value, int $decimals): string
    {
        $formatted = rtrim(rtrim(sprintf("%.{$decimals}F", $value), '0'), '.');

        return $formatted === '-0' || $formatted === '' ? '0' : $formatted;
    }

    public static function alpha(float $alpha): string
    {
        return $alpha < 1.0 ? ' / '.self::number($alpha, 3) : '';
    }
}
