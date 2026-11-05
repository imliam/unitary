<?php

declare(strict_types=1);

namespace Unitary\Laravel\Casts;

use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Unitary\Quantity;

/**
 * @internal
 */
final class Decimal
{
    /**
     * The exact decimal when it fits in Quantity::DEFAULT_PRECISION places,
     * otherwise rounded to that many significant digits.
     */
    public static function toString(BigRational $value, int $precision = Quantity::DEFAULT_PRECISION): string
    {
        try {
            $exact = $value->toBigDecimal();

            if ($exact->getScale() <= $precision) {
                return (string) $exact;
            }
        } catch (RoundingNecessaryException) {
        }

        if ($value->isZero()) {
            return '0';
        }

        $absolute = $value->abs();
        $exponent = strlen((string) $absolute->getNumerator()) - strlen((string) $absolute->getDenominator());

        if ($absolute->isLessThan(self::powerOfTen($exponent))) {
            $exponent--;
        }

        return (string) $value->toScale(max(0, $precision - 1 - $exponent), RoundingMode::HalfUp)->strippedOfTrailingZeros();
    }

    private static function powerOfTen(int $exponent): BigRational
    {
        $power = BigRational::ten()->power(abs($exponent));

        return $exponent < 0 ? $power->reciprocal() : $power;
    }
}
