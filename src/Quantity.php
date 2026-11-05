<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;
use Unitary\Currency\Currency;
use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Formatting\UnitNames;
use Unitary\Parsing\NumberParser;
use Unitary\Preferences\UnitPreferences;
use Unitary\Support\Exact;
use Unitary\Units\Ratio;
use Unitary\Units\Temperature;
use Unitary\Units\TemperatureDifference;

/**
 * An immutable amount of something in a given unit, e.g. 1000 miles or £23.
 *
 * Values are stored as exact rationals, so chains of conversions never lose
 * precision; rounding only happens when you ask for a decimal or a string.
 *
 * A quantity may also carry an absolute uncertainty (5.0 ± 0.1 m), which is
 * propagated linearly (worst case) through conversions and arithmetic, and
 * the number of significant figures it was written with.
 *
 * @template-covariant TUnit of Unit
 */
final readonly class Quantity implements JsonSerializable, Stringable
{
    /**
     * Significant digits used when the exact value has no short decimal form
     * (e.g. 1/3, or anything derived from π).
     */
    public const DEFAULT_PRECISION = 20;

    private BigRational $value;

    private ?BigRational $uncertainty;

    /**
     * @param  TUnit  $unit
     */
    public function __construct(
        BigNumber|int|float|string $value,
        private Unit $unit,
        private ?Converter $converter = null,
        BigNumber|int|float|string|null $uncertainty = null,
        private ?int $significantFigures = null,
    ) {
        $this->value = NumberParser::parse($value);
        $this->uncertainty = $uncertainty === null ? null : NumberParser::parse($uncertainty)->abs();
    }

    /**
     * @template TOf of Unit
     *
     * @param  TOf|string  $unit
     * @return ($unit is string ? self<Unit> : self<TOf>)
     */
    public static function of(BigNumber|int|float|string $value, Unit|string $unit): self
    {
        return Unitary::converter()->quantity($value, $unit);
    }

    /**
     * @return self<Unit>
     */
    public static function parse(string $input): self
    {
        return Unitary::converter()->parseQuantity($input);
    }

    /**
     * @return TUnit
     */
    public function unit(): Unit
    {
        return $this->unit;
    }

    public function dimension(): Dimension
    {
        return $this->unit->dimension();
    }

    /** The unit's full name, e.g. "kilometer", "Pound sterling", "kilometer per hour". */
    public function unitName(): string
    {
        return $this->unit->name();
    }

    /** The exact value. */
    public function value(): BigRational
    {
        return $this->value;
    }

    /** The absolute uncertainty in this quantity's unit, if known. */
    public function uncertainty(): ?BigRational
    {
        return $this->uncertainty;
    }

    /** The number of significant figures the value was written with, if known. */
    public function significantFigures(): ?int
    {
        return $this->significantFigures;
    }

    /**
     * @param  BigNumber|int|float|string|self<Unit>|null  $uncertainty
     * @return self<TUnit>
     */
    public function withUncertainty(BigNumber|int|float|string|self|null $uncertainty): self
    {
        $uncertainty = $uncertainty instanceof self ? $uncertainty->to($this->unit)->value : $uncertainty;

        return new self($this->value, $this->unit, $this->converter, $uncertainty, $this->significantFigures);
    }

    /**
     * @return self<TUnit>
     */
    public function withSignificantFigures(?int $significantFigures): self
    {
        if ($significantFigures !== null && $significantFigures < 1) {
            throw new InvalidArgumentException('Significant figures must be at least 1');
        }

        return new self($this->value, $this->unit, $this->converter, $this->uncertainty, $significantFigures);
    }

    /**
     * Rounds the value to the given (or tracked) number of significant figures.
     *
     * @return self<TUnit>
     */
    public function roundToSignificantFigures(?int $significantFigures = null): self
    {
        $significantFigures ??= $this->significantFigures ?? self::DEFAULT_PRECISION;
        $rounded = $this->value->isZero() ? $this->value : $this->value->toScale(max(0, $significantFigures - 1 - $this->exponent()), RoundingMode::HalfUp)->toBigRational();

        return new self($rounded, $this->unit, $this->converter, $this->uncertainty, $significantFigures);
    }

    /**
     * The value as a decimal. With no scale, the exact value is returned when it
     * terminates within DEFAULT_PRECISION decimal places; otherwise it's rounded
     * to DEFAULT_PRECISION significant digits.
     */
    public function toDecimal(?int $scale = null, RoundingMode $roundingMode = RoundingMode::HalfUp): BigDecimal
    {
        if ($scale !== null) {
            return $this->value->toScale($scale, $roundingMode);
        }

        try {
            $exact = $this->value->toBigDecimal();

            if ($exact->getScale() <= self::DEFAULT_PRECISION) {
                return $exact;
            }
        } catch (RoundingNecessaryException) {
        }

        $scale = $this->value->isZero() ? 0 : max(0, self::DEFAULT_PRECISION - 1 - $this->exponent());

        return $this->value->toScale($scale, $roundingMode)->strippedOfTrailingZeros();
    }

    public function toFloat(): float
    {
        return $this->value->toFloat();
    }

    /**
     * Converts to another unit of the same dimension. Currencies can be
     * converted at a historical date when the provider supports it.
     *
     * @template TTo of Unit
     *
     * @param  TTo|string  $unit
     * @return ($unit is string ? self<Unit> : self<TTo>)
     */
    public function to(Unit|string $unit, ?DateTimeInterface $at = null): self
    {
        return $this->converter()->convertQuantity($this, $unit, $at);
    }

    /**
     * Converts to the coherent SI unit for this dimension: J/s → W,
     * m⋅ft → m², km/h → m/s, m/ft → 1.
     *
     * @return self<Unit>
     */
    public function simplify(): self
    {
        return $this->to($this->converter()->units()->coherent($this->unit));
    }

    /**
     * @return self<TUnit>
     */
    public function withConverter(Converter $converter): self
    {
        return new self($this->value, $this->unit, $converter, $this->uncertainty, $this->significantFigures);
    }

    /**
     * Applies a conversion function, carrying uncertainty through it.
     *
     * @internal
     *
     * @template TTo of Unit
     *
     * @param  Closure(BigRational): BigRational  $conversion
     * @param  TTo  $unit
     * @return self<TTo>
     */
    public function mapped(Closure $conversion, Unit $unit, Converter $converter): self
    {
        $value = $conversion($this->value)->simplified();
        $uncertainty = $this->uncertainty === null
            ? null
            : $conversion($this->value->plus($this->uncertainty))->minus($value)->abs()->simplified();

        return new self($value, $unit, $converter, $uncertainty, $this->significantFigures);
    }

    /**
     * Adding to a temperature treats the other side as a change: 20 °C + 5 °F
     * is 20 °C warmed by 5 Fahrenheit degrees, 22.78 °C. A difference plus a
     * temperature is a temperature (the PHPStan extension knows this).
     *
     * @param  self<Unit>|BigNumber|int|float|string  $that
     * @return self<TUnit>
     */
    public function plus(self|BigNumber|int|float|string $that): self
    {
        // A difference plus a temperature is that temperature, shifted.
        if ($this->unit instanceof TemperatureDifference && $that instanceof self && $that->unit instanceof Temperature) {
            return $that->plus($this); // @phpstan-ignore return.type (the extension types this as the temperature)
        }

        [$value, $uncertainty] = $this->operand($that, asChange: true);

        return $this->derive($this->value->plus($value), $this->unit, self::sum($this->uncertainty, $uncertainty), $that);
    }

    /**
     * Subtracting one temperature from another gives the change between them:
     * 30 °C − 10 °C = 20 Δ°C, which is 36 Δ°F rather than 68 °F (the PHPStan
     * extension knows this).
     *
     * @param  self<Unit>|BigNumber|int|float|string  $that
     * @return self<TUnit>
     */
    public function minus(self|BigNumber|int|float|string $that): self
    {
        if ($this->unit instanceof TemperatureDifference && $that instanceof self && $that->unit instanceof Temperature) {
            throw IncompatibleUnitsException::between($that->unit, $this->unit);
        }

        $difference = $this->unit instanceof Temperature && $that instanceof self && $that->unit instanceof Temperature;
        [$value, $uncertainty] = $this->operand($that, asChange: ! $difference);
        $unit = $difference ? $this->unit->difference() : $this->unit;

        return $this->derive($this->value->minus($value), $unit, self::sum($this->uncertainty, $uncertainty), $that); // @phpstan-ignore return.type (the extension types temperature − temperature as a difference)
    }

    /**
     * Multiplies by a number, or by another quantity to get a derived quantity:
     * 3 m × 4 m = 12 m², $120/h × 42 h = $5,040.
     *
     * @param  self<Unit>|BigNumber|int|float|string  $that
     * @return ($that is self<Unit> ? self<Unit> : self<TUnit>)
     */
    public function multipliedBy(self|BigNumber|int|float|string $that): self
    {
        if (! $that instanceof self) {
            $factor = NumberParser::parse($that);

            return $this->derive($this->value->multipliedBy($factor), $this->unit, $this->uncertainty?->multipliedBy($factor->abs()));
        }

        [$value, $unit, $scale] = $this->combine($that, 1);

        // First-order propagation: δ(ab) = |b|δa + |a|δb.
        $uncertainty = self::sum(
            $this->uncertainty?->multipliedBy($that->value->abs()),
            $that->uncertainty?->multipliedBy($this->value->abs()),
        )?->multipliedBy($scale);

        return $this->derive($value, $unit, $uncertainty, $that);
    }

    /**
     * Divides by a number, or by another quantity: 90 km ÷ 3 h = 30 km/h.
     *
     * @param  self<Unit>|BigNumber|int|float|string  $that
     * @return ($that is self<Unit> ? self<Unit> : self<TUnit>)
     */
    public function dividedBy(self|BigNumber|int|float|string $that): self
    {
        if (! $that instanceof self) {
            $divisor = NumberParser::parse($that);

            return $this->derive($this->value->dividedBy($divisor), $this->unit, $this->uncertainty?->dividedBy($divisor->abs()));
        }

        [$value, $unit, $scale] = $this->combine($that, -1);

        // δ(a/b) = δa/|b| + |a|δb/b².
        $uncertainty = self::sum(
            $this->uncertainty?->dividedBy($that->value->abs()),
            $that->uncertainty?->multipliedBy($this->value->abs())->dividedBy($that->value->power(2)),
        )?->multipliedBy($scale);

        return $this->derive($value, $unit, $uncertainty, $that);
    }

    /**
     * @return self<Unit>
     */
    public function power(int $exponent): self
    {
        $unit = $this->converter()->units()->canonical(CompoundUnit::power($this->unit, $exponent));
        $value = Exact::power($this->value, $exponent);

        // δ(aⁿ) = |n|·|a|ⁿ⁻¹·δa.
        $uncertainty = $this->uncertainty === null || $this->value->isZero()
            ? $this->uncertainty
            : Exact::power($this->value->abs(), $exponent - 1)->multipliedBy(abs($exponent))->multipliedBy($this->uncertainty);

        return $this->derive($value, $unit, $uncertainty);
    }

    /**
     * @return self<TUnit>
     */
    public function negated(): self
    {
        return $this->derive($this->value->negated(), $this->unit, $this->uncertainty);
    }

    /**
     * @return self<TUnit>
     */
    public function abs(): self
    {
        return $this->derive($this->value->abs(), $this->unit, $this->uncertainty);
    }

    /**
     * Compares against another quantity (converted into this unit) or a plain number.
     *
     * @param  self<Unit>|BigNumber|int|float|string  $that
     */
    public function compareTo(self|BigNumber|int|float|string $that): int
    {
        return $this->value->compareTo($this->operand($that)[0]);
    }

    /**
     * @param  self<Unit>|BigNumber|int|float|string  $that
     */
    public function isEqualTo(self|BigNumber|int|float|string $that): bool
    {
        return $this->compareTo($that) === 0;
    }

    /**
     * @param  self<Unit>|BigNumber|int|float|string  $that
     */
    public function isGreaterThan(self|BigNumber|int|float|string $that): bool
    {
        return $this->compareTo($that) > 0;
    }

    /**
     * @param  self<Unit>|BigNumber|int|float|string  $that
     */
    public function isGreaterThanOrEqualTo(self|BigNumber|int|float|string $that): bool
    {
        return $this->compareTo($that) >= 0;
    }

    /**
     * @param  self<Unit>|BigNumber|int|float|string  $that
     */
    public function isLessThan(self|BigNumber|int|float|string $that): bool
    {
        return $this->compareTo($that) < 0;
    }

    /**
     * @param  self<Unit>|BigNumber|int|float|string  $that
     */
    public function isLessThanOrEqualTo(self|BigNumber|int|float|string $that): bool
    {
        return $this->compareTo($that) <= 0;
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->value->isPositive();
    }

    /**
     * Splits into whole parts of descending units, e.g. 1.83 m → 6 ft 0.05 in.
     * Without units, sensible defaults are used for length, mass, time and volume.
     */
    public function breakdown(Unit|string ...$units): Breakdown
    {
        return Breakdown::of($this, ...$units);
    }

    /**
     * Picks the most readable unit, e.g. 0.00002 m → 20 µm, 90000 s → 1.0416 d.
     * Stays within the current unit's system unless one is given.
     *
     * @return self<Unit>
     */
    public function humanize(?UnitSystem $system = null): self
    {
        return Humanizer::humanize($this, $system);
    }

    /**
     * The unit a region uses for this kind of value, from CLDR: 80 km for
     * "en-GB" on the road is 49.71 mi, and for "sv-SE" it's 8 mil. Mixed units
     * like feet and inches come back as a Breakdown.
     *
     * @param  string  $locale  a locale ("en-GB") or region ("GB")
     * @param  string  $usage  "road", "person-height", "weather"... (see UnitPreferences::usages())
     * @return self<Unit>|Breakdown
     */
    public function forLocale(string $locale, string $usage = 'default'): self|Breakdown
    {
        return UnitPreferences::apply($this, $locale, $usage);
    }

    /** @return self<Unit> */
    public function toMetric(): self
    {
        return Humanizer::humanize($this, UnitSystem::Metric);
    }

    /** @return self<Unit> */
    public function toImperial(): self
    {
        return Humanizer::humanize($this, UnitSystem::Imperial);
    }

    /** @return self<Unit> */
    public function toUsCustomary(): self
    {
        return Humanizer::humanize($this, UnitSystem::UsCustomary);
    }

    /**
     * Human-friendly output with thousands separators, e.g. "1,609.344 m" or "£23.00".
     * Currencies default to their minor units and display symbol.
     *
     * With a locale ("de", "fr_FR") the separators come from ext-intl's
     * NumberFormatter: "1.609,344 m". Only its symbols are used, so values stay
     * exact. Explicit separators override the locale.
     *
     * With a $display (narrow, short or long), the unit is written the way CLDR
     * writes it in that locale: "2″", "2 in", "2 inches", or "2 pouces" in
     * French. Without one, the unit is Unitary's symbol, whatever the locale.
     */
    public function format(
        ?int $decimals = null,
        ?string $decimalPoint = null,
        ?string $thousandsSeparator = null,
        bool $withUnit = true,
        ?string $locale = null,
        UnitDisplay|string|null $display = null,
    ): string {
        [$localeDecimalPoint, $localeThousandsSeparator] = self::separators($locale);
        $decimalPoint ??= $localeDecimalPoint;
        $thousandsSeparator ??= $localeThousandsSeparator;
        $currency = $this->unit instanceof Currency ? $this->unit : null;
        $decimals ??= $currency?->minorUnits();
        $decimal = $this->toDecimal($decimals);
        $number = self::group($decimal, $decimalPoint, $thousandsSeparator);

        if ($this->uncertainty !== null) {
            $number .= ' ± '.self::group($this->uncertainty->toScale($decimals ?? $this->uncertaintyScale(), RoundingMode::HalfUp), $decimalPoint, $thousandsSeparator);
        }

        if (! $withUnit) {
            return $number;
        }

        if ($display !== null) {
            $display = is_string($display) ? UnitDisplay::from($display) : $display;

            return UnitNames::join($number, $decimal->toBigRational(), $decimal->getScale(), $this->unit, $display, $locale);
        }

        if ($currency !== null && $this->uncertainty === null) {
            $symbol = $currency->displaySymbol();

            // "£23.00", "-US$5.00" but "23.00 zł", "23.00 CHF".
            if (preg_match('/^\P{L}|\$$/u', $symbol)) {
                return str_starts_with($number, '-') ? '-'.$symbol.substr($number, 1) : $symbol.$number;
            }

            return "{$number} {$symbol}";
        }

        return $this->joinUnit($number);
    }

    /**
     * The value to a number of significant figures (the tracked count by default), e.g. "2.50 kg".
     */
    public function toPrecision(?int $significantFigures = null, bool $withUnit = true): string
    {
        $significantFigures ??= $this->significantFigures ?? self::DEFAULT_PRECISION;
        $scale = $this->value->isZero() ? $significantFigures - 1 : max(0, $significantFigures - 1 - $this->exponent());
        $number = (string) $this->value->toScale($scale, RoundingMode::HalfUp);

        return $withUnit ? $this->joinUnit($number) : $number;
    }

    /**
     * Scientific notation for very large or very small values, e.g. "1.609344e+15 nm".
     */
    public function toScientific(int $significantDigits = 10, bool $withUnit = true): string
    {
        $significantDigits = max(1, $significantDigits);

        if ($this->value->isZero()) {
            $number = '0';
        } else {
            $exponent = $this->exponent();
            $mantissa = $this->value->abs()->dividedBy(Exact::power(BigRational::ten(), $exponent))
                ->toScale($significantDigits - 1, RoundingMode::HalfUp);

            if ($mantissa->isGreaterThanOrEqualTo(10)) {
                $exponent++;
                $mantissa = $mantissa->dividedBy(10, $significantDigits - 1, RoundingMode::HalfUp);
            }

            $number = ($this->value->isNegative() ? '-' : '')
                .$mantissa->strippedOfTrailingZeros()
                .'e'.($exponent < 0 ? '-' : '+').abs($exponent);
        }

        return $withUnit ? $this->joinUnit($number) : $number;
    }

    /**
     * A plain, parseable representation, e.g. "1609.344 m", "23 GBP" or
     * "5.00 ± 0.10 m" (uncertainty to two significant figures).
     */
    public function toString(): string
    {
        if ($this->uncertainty !== null) {
            $scale = $this->uncertaintyScale();

            return $this->joinUnit($this->value->toScale($scale, RoundingMode::HalfUp).' ± '.$this->uncertainty->toScale($scale, RoundingMode::HalfUp));
        }

        return $this->joinUnit((string) $this->toDecimal());
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{value: string, unit: string, unitName: string, dimension: string, uncertainty?: string}
     */
    public function jsonSerialize(): array
    {
        $json = [
            'value' => (string) $this->toDecimal(),
            'unit' => $this->unit->symbol(),
            'unitName' => $this->unit->name(),
            'dimension' => $this->unit->dimension()->value,
        ];

        if ($this->uncertainty !== null) {
            $json['uncertainty'] = (string) (new self($this->uncertainty, $this->unit))->toDecimal();
        }

        return $json;
    }

    /** floor(log10(|value|)) for a non-zero value. */
    private function exponent(?BigRational $of = null): int
    {
        $absolute = ($of ?? $this->value)->abs();
        $exponent = strlen((string) $absolute->getNumerator()) - strlen((string) $absolute->getDenominator());

        return $absolute->isLessThan(Exact::power(BigRational::ten(), $exponent)) ? $exponent - 1 : $exponent;
    }

    /** Decimal places that show the uncertainty to two significant figures. */
    private function uncertaintyScale(): int
    {
        if ($this->uncertainty === null || $this->uncertainty->isZero()) {
            return 0;
        }

        return max(0, 1 - $this->exponent($this->uncertainty));
    }

    private function joinUnit(string $number): string
    {
        $symbol = $this->unit->symbol();

        if ($symbol === '') {
            return $number;
        }

        // Degree-style symbols hug the number: 90°, 5′, 11″, 50%.
        return preg_match('/^[°′″\'"%‰]$/u', $symbol) ? $number.$symbol : "{$number} {$symbol}";
    }

    /**
     * @return array{string, string} decimal point and thousands separator
     */
    private static function separators(?string $locale): array
    {
        if ($locale === null || ! class_exists(\NumberFormatter::class)) {
            return ['.', ','];
        }

        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        return [
            $formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
            $formatter->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
        ];
    }

    private static function group(BigDecimal $decimal, string $decimalPoint, string $thousandsSeparator): string
    {
        [$whole, $fraction] = array_pad(explode('.', $decimal->abs()->toString(), 2), 2, '');

        return ($decimal->isNegative() ? '-' : '')
            .preg_replace('/\B(?=(\d{3})+(?!\d))/', $thousandsSeparator, $whole)
            .($fraction !== '' ? $decimalPoint.$fraction : '');
    }

    /**
     * @template TTo of Unit
     *
     * @param  TTo  $unit
     * @param  self<Unit>|BigNumber|int|float|string|null  $other
     * @return self<TTo>
     */
    private function derive(BigRational $value, Unit $unit, ?BigRational $uncertainty, self|BigNumber|int|float|string|null $other = null): self
    {
        $significantFigures = $this->significantFigures;

        if ($other instanceof self && $other->significantFigures !== null) {
            $significantFigures = min($significantFigures ?? PHP_INT_MAX, $other->significantFigures);
        }

        return new self($value->simplified(), $unit, $this->converter, $uncertainty?->simplified(), $significantFigures);
    }

    /**
     * Multiplies (sign 1) or divides (sign −1) by another quantity, expanding
     * named units into parts and converting like dimensions into one unit so
     * they merge or cancel: m × ft → m², km/h × min → km.
     *
     * @param  self<Unit>  $that
     * @return array{BigRational, Unit, BigRational} the value, unit, and the scale applied by aligning units
     */
    private function combine(self $that, int $sign): array
    {
        $registry = $this->converter()->units();
        $factors = [];

        foreach ([[$this->unit, 1], [$that->unit, $sign]] as [$unit, $power]) {
            $expanded = $registry->expand($unit);

            foreach ($expanded instanceof CompoundUnit ? $expanded->factors : [['unit' => $expanded, 'power' => 1]] as $factor) {
                $factors[] = ['unit' => $factor['unit'], 'power' => $factor['power'] * $power];
            }
        }

        // Express every factor in the first unit of its dimension.
        $scale = BigRational::one();
        $chosen = [];

        foreach ($factors as $index => $factor) {
            $unit = $factor['unit'];

            if (! $unit instanceof MeasurementUnit || ! $unit->definition()->isLinear()) {
                continue;
            }

            $first = $chosen[$unit->dimension()->value] ??= $unit;

            if ($first !== $unit) {
                $ratio = CompoundUnit::coherentFactorOf($unit)->dividedBy(CompoundUnit::coherentFactorOf($first));
                $scale = $scale->multipliedBy(Exact::power($ratio, $factor['power']));
                $factors[$index]['unit'] = $first;
            }
        }

        $raw = $sign > 0 ? $this->value->multipliedBy($that->value) : $this->value->dividedBy($that->value);
        $unit = $registry->canonical(new CompoundUnit($factors));

        return [$raw->multipliedBy($scale), $unit, $scale->abs()];
    }

    /**
     * The other side of a sum, in this quantity's unit. With $asChange, a
     * temperature added to a temperature is read as a difference on its scale.
     *
     * @param  self<Unit>|BigNumber|int|float|string  $that
     * @return array{BigRational, ?BigRational}
     */
    private function operand(self|BigNumber|int|float|string $that, bool $asChange = false): array
    {
        if (! $that instanceof self) {
            return [NumberParser::parse($that), null];
        }

        if ($this->unit instanceof Temperature && ($asChange || $that->unit instanceof TemperatureDifference)) {
            $change = $that->unit instanceof Temperature
                ? new self($that->value, $that->unit->difference(), $this->converter, $that->uncertainty)
                : $that;
            $converted = $change->to($this->unit->difference());

            return [$converted->value, $converted->uncertainty];
        }

        $converted = $that->to($this->unit);

        return [$converted->value, $converted->uncertainty];
    }

    private static function sum(?BigRational $a, ?BigRational $b): ?BigRational
    {
        return $a === null ? $b : ($b === null ? $a : $a->plus($b));
    }

    private function converter(): Converter
    {
        return $this->converter ?? Unitary::converter();
    }
}
