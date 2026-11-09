<?php

declare(strict_types=1);

namespace Unitary\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Natural logarithms and exponentials to PRECISION decimal places, for
 * logarithmic units. Results are exact whenever the answer is an exact
 * power of the base (20 dBm is exactly 100 mW, 1000 Hz is exactly 3 decades).
 *
 * @internal
 */
final class Transcendental
{
    public const PRECISION = 50;

    private const GUARD = 10;

    /** baseˣ, exact for integer x. */
    public static function power(string $base, BigRational $exponent): BigRational
    {
        if ($base !== 'e' && $exponent->getDenominator()->isEqualTo(1)) {
            return Exact::power(BigRational::of($base), $exponent->getNumerator()->toInt());
        }

        // baseˣ = 10ⁿ × 10ᶠ with n whole and f in [0, 1), keeping relative precision for huge and tiny results.
        $lnBase = $base === 'e' ? BigDecimal::one() : self::ln(BigRational::of($base));
        $decades = $exponent->toScale(self::scale(), RoundingMode::HalfEven)->multipliedBy($lnBase)
            ->dividedBy(self::ln10(), self::scale(), RoundingMode::HalfEven);
        $whole = $decades->toScale(0, RoundingMode::Floor);
        $fraction = $decades->minus($whole);

        $mantissa = self::exp($fraction->multipliedBy(self::ln10())->toScale(self::scale(), RoundingMode::HalfEven));

        return self::round($mantissa)->multipliedBy(Exact::power(BigRational::ten(), $whole->toBigInteger()->toInt()));
    }

    /** log_base(x), exact when x is an integer power of the base. */
    public static function log(string $base, BigRational $value): BigRational
    {
        if ($value->isNegativeOrZero()) {
            throw new InvalidArgumentException('Logarithms are only defined for positive values');
        }

        $result = $base === 'e'
            ? self::ln($value)->toScale(self::scale(), RoundingMode::HalfEven)
            : self::ln($value)->dividedBy(self::ln(BigRational::of($base)), self::scale(), RoundingMode::HalfEven);

        if ($base !== 'e') {
            $nearest = $result->toScale(0, RoundingMode::HalfEven)->toBigInteger()->toInt();

            if (Exact::power(BigRational::of($base), $nearest)->isEqualTo($value)) {
                return BigRational::of($nearest);
            }
        }

        return self::round($result);
    }

    private static function ln(BigRational $value): BigDecimal
    {
        $scale = self::scale();

        // x = m × 10ᵏ with m in [1, 10), so tiny and huge values keep their precision.
        $decades = strlen((string) $value->getNumerator()) - strlen((string) $value->getDenominator());
        $mantissa = $value->dividedBy(Exact::power(BigRational::ten(), $decades));

        if ($mantissa->isLessThan(1)) {
            $decades--;
            $mantissa = $mantissa->multipliedBy(10);
        }

        if ($decades !== 0) {
            return self::ln($mantissa)->plus(self::ln10()->multipliedBy($decades))->toScale($scale, RoundingMode::HalfEven);
        }

        $x = $value->toScale($scale, RoundingMode::HalfEven);

        // x = m × 2ᵏ with m in [1, 2): ln x = k·ln 2 + ln m.
        $k = 0;

        while ($x->isGreaterThanOrEqualTo(2)) {
            $x = $x->dividedBy(2, $scale, RoundingMode::HalfEven);
            $k++;
        }

        while ($x->isLessThan(1)) {
            $x = $x->multipliedBy(2);
            $k--;
        }

        return self::atanhLog($x)->plus(self::ln2()->multipliedBy($k))->toScale($scale, RoundingMode::HalfEven);
    }

    private static function exp(BigDecimal $x): BigDecimal
    {
        $scale = self::scale();

        // exp(x) = exp(x / 2ⁿ)^(2ⁿ) keeps the series argument small.
        $halvings = 0;

        while ($x->abs()->isGreaterThan('0.5')) {
            $x = $x->dividedBy(2, $scale, RoundingMode::HalfEven);
            $halvings++;
        }

        $sum = BigDecimal::one();
        $term = BigDecimal::one();

        for ($n = 1; $n < 500; $n++) {
            $term = $term->multipliedBy($x)->dividedBy($n, $scale, RoundingMode::HalfEven);

            if ($term->isZero()) {
                break;
            }

            $sum = $sum->plus($term);
        }

        for ($i = 0; $i < $halvings; $i++) {
            $sum = $sum->multipliedBy($sum)->toScale($scale, RoundingMode::HalfEven);
        }

        return $sum;
    }

    /** ln m = 2·atanh((m − 1)/(m + 1)), which converges quickly for m in [1, 2). */
    private static function atanhLog(BigDecimal $m): BigDecimal
    {
        $scale = self::scale();
        $z = $m->minus(1)->dividedBy($m->plus(1), $scale, RoundingMode::HalfEven);
        $zSquared = $z->multipliedBy($z)->toScale($scale, RoundingMode::HalfEven);
        $power = $z;
        $sum = BigDecimal::zero();

        for ($n = 1; $n < 2000; $n += 2) {
            $term = $power->dividedBy($n, $scale, RoundingMode::HalfEven);

            if ($term->isZero()) {
                break;
            }

            $sum = $sum->plus($term);
            $power = $power->multipliedBy($zSquared)->toScale($scale, RoundingMode::HalfEven);
        }

        return $sum->multipliedBy(2);
    }

    private static function ln10(): BigDecimal
    {
        /** @var BigDecimal|null $ln10 */
        static $ln10 = null;

        // ln 10 = 3·ln 2 + ln 1.25, without going through ln(), which itself needs ln 10.
        return $ln10 ??= self::ln2()->multipliedBy(3)->plus(self::atanhLog(BigDecimal::of('1.25')));
    }

    private static function ln2(): BigDecimal
    {
        /** @var BigDecimal|null $ln2 */
        static $ln2 = null;

        return $ln2 ??= self::atanhLog(BigDecimal::of(2));
    }

    /** @return non-negative-int */
    private static function scale(): int
    {
        return self::PRECISION + self::GUARD;
    }

    private static function round(BigDecimal $value): BigRational
    {
        return $value->toScale(self::PRECISION, RoundingMode::HalfEven)->strippedOfTrailingZeros()->toBigRational();
    }
}
