<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

/**
 * @internal
 */
final class Steps
{
    /** Rounds to the nearest multiple of $step (e.g. 1/2 for half sizes). */
    public static function round(BigRational $value, BigRational|string $step): BigRational
    {
        $step = BigRational::of($step);

        return BigRational::of($value->dividedBy($step)->toScale(0, RoundingMode::HalfUp))->multipliedBy($step);
    }

    /** "9", "9.5", "43" — trailing zeros trimmed. */
    public static function format(BigRational $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp)->strippedOfTrailingZeros();
    }
}
