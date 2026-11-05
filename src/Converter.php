<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Brick\Math\Exception\MathException;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Unitary\Calc\Calculator;
use Unitary\Color\Color;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRateLookup;
use Unitary\Currency\ExchangeRateProvider;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Exceptions\ExchangeRateUnavailableException;
use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Exceptions\ParseException;
use Unitary\Exceptions\UnitaryException;
use Unitary\Exceptions\UnknownUnitException;
use Unitary\Geo\Coordinate;
use Unitary\Geo\CoordinateFormat;
use Unitary\Geo\Distance;
use Unitary\Inflation\Inflation;
use Unitary\Parsing\NumberParser;
use Unitary\Parsing\QuantityParser;
use Unitary\Scales\Scale;
use Unitary\Scales\Scales;
use Unitary\Substances\SubstanceConverter;
use Unitary\Time\Timezone;
use Unitary\Time\ZonedTime;
use Unitary\Units\Temperature;
use Unitary\Units\TemperatureDifference;


/**
 * Does the actual work behind Unitary::convert(). Create your own instance
 * to use a custom unit registry or exchange rate provider without touching
 * the global default.
 */
final class Converter
{
    /** Words and arrows that separate a source from a target: "5 km to mi", "£23 in USD". */
    private const TARGET_SEPARATOR = '/\s(?:to|in|into|as|in terms of)(?=\s)|(?:->|→|=>|=)/iu';

    /** A trailing date for historical exchange rates: "£23 in USD on 2020-03-01". */
    private const DATE_SUFFIX = '/\s+(?:on|at|as of|as at)\s+(?<date>[^,]*\d[^,]*)$/iu';

    private readonly QuantityParser $parser;

    /** Set while the calculator fallback runs, since the calculator calls parse() itself. */
    private bool $calculating = false;

    public function __construct(
        private readonly UnitRegistry $units = new UnitRegistry(),
        private ?ExchangeRateProvider $exchangeRates = null,
    ) {
        $this->parser = new QuantityParser($this->units, $this);
    }

    public function units(): UnitRegistry
    {
        return $this->units;
    }

    public function exchangeRates(): ?ExchangeRateProvider
    {
        return $this->exchangeRates;
    }

    public function useExchangeRates(?ExchangeRateProvider $provider): self
    {
        $this->exchangeRates = $provider;

        return $this;
    }

    /**
     * The flexible entry point. Accepts:
     *  - a number and a unit:            (23, 'gbp'), ('23', Currency::GBP), (1000, Length::Mile)
     *  - freeform text:                  ('£23'), ('23 GBP'), ("13' 11\""), ('5 ft 11 in'), ('two and a half miles')
     *  - freeform text with a target:    ('£23 in USD'), ('100 km/h to mph'), ('$20/day in /month')
     *  - historical money:               ('£23 in USD on 2020-03-01'), ('£100 in 1970 in today\'s money')
     *  - colors:                         ('#ff0000'), ('rgb(255 0 0) to oklch'), ('rebeccapurple')
     *  - times in a zone:                ('10:00', 'Europe/London'), ('9am PST in Tokyo'), ($dateTime, 'UTC')
     *  - coordinates and distances:      ('51°30′26″N 0°7′39″W to decimal'), ('51.5,-0.12 to 48.85,2.35 in miles')
     *  - sizes and scales:               ('UK 9 shoe in EU'), ('A4 in inches'), ('gas mark 4 in °F')
     *  - substances:                     ('1 cup of flour in g'), ('10 gal of diesel in lb CO2')
     *
     * @throws ParseException|UnknownUnitException|IncompatibleUnitsException|ExchangeRateUnavailableException
     *
     * @return \Unitary\Quantity<\Unitary\Unit>|Color|ZonedTime|Coordinate|Scale
     */
    public function convert(
        BigNumber|int|float|string|DateTimeInterface $value,
        Unit|DateTimeZone|string|null $unit = null,
    ): Quantity|Color|ZonedTime|Coordinate|Scale {
        if ($value instanceof DateTimeInterface) {
            return ZonedTime::of($value, $unit instanceof Unit ? throw ParseException::cannotParse($unit->symbol(), 'timezone') : $unit);
        }

        if ($unit instanceof DateTimeZone) {
            // A bare number alongside a timezone is a Unix timestamp.
            return ZonedTime::of(is_string($value) && NumberParser::tryParse($value) === null ? $value : '@'.$value, $unit);
        }

        if ($unit === null) {
            if (! is_string($value)) {
                throw ParseException::cannotParse((string) $value, 'quantity', 'a unit is required');
            }

            return $this->parse($value);
        }

        if (is_string($value) && NumberParser::tryParse($value) === null) {
            if (is_string($unit) && ($zone = Timezone::tryResolve($unit)) !== null) {
                return ZonedTime::of($value, $zone);
            }

            throw ParseException::cannotParse($value, 'number');
        }

        return $this->quantity($value, $unit);
    }

    /**
     * Creates a quantity from a number and a unit (enum case or text).
     * Numeric strings keep their significant figures ("2.50" has 3).
     *
     * @template TUnit of Unit
     *
     * @param  TUnit|string  $unit
     * @return ($unit is string ? Quantity<Unit> : Quantity<TUnit>)
     */
    public function quantity(BigNumber|int|float|string $value, Unit|string $unit): Quantity
    {
        $significantFigures = is_string($value) ? NumberParser::significantFigures($value) : null;

        return new Quantity($value, $this->units->resolve($unit), $this, significantFigures: $significantFigures);
    }

    /**
     * Converts a quantity to another unit. Text units are resolved preferring the
     * quantity's dimension, so "m" means minutes when converting from hours.
     *
     * @return Quantity<Unit>
     *
     * @param  \Unitary\Quantity<\Unitary\Unit>  $quantity
     */
    public function convertQuantity(Quantity $quantity, Unit|string $to, ?DateTimeInterface $at = null): Quantity
    {
        $from = $quantity->unit();
        $to = $this->units->canonical($this->units->resolve($to, $from->dimension()));

        // A change in temperature can't be a reading, so "20 Δ°C in °F" means Fahrenheit degrees.
        if ($from instanceof TemperatureDifference && $to instanceof Temperature) {
            $to = $to->difference();
        }

        if ($from === $to) {
            return $quantity->withConverter($this);
        }

        return $quantity->mapped($this->conversion($from, $to, $at), $to, $this);
    }

    /**
     * The function that converts a value from one unit to another.
     *
     * @return Closure(BigRational): BigRational
     *
     * @throws IncompatibleUnitsException|ExchangeRateUnavailableException
     */
    public function conversion(Unit $from, Unit $to, ?DateTimeInterface $at = null): Closure
    {
        if (! self::compatible($from, $to)) {
            throw IncompatibleUnitsException::between($from, $to);
        }

        if ($from instanceof MeasurementUnit && $to instanceof MeasurementUnit) {
            return fn (BigRational $value) => $to->fromBase($from->toBase($value));
        }

        // Compounds and currencies: scale by coherent factors, then by an exchange rate if needed.
        $factor = CompoundUnit::coherentFactorOf($from)->dividedBy(CompoundUnit::coherentFactorOf($to));
        $fromCurrency = self::currencyOf($from);
        $toCurrency = self::currencyOf($to);

        if ($fromCurrency !== null && $toCurrency !== null && $fromCurrency[0] !== $toCurrency[0]) {
            $provider = $this->exchangeRates ?? throw ExchangeRateUnavailableException::noProvider();
            $rate = ExchangeRateLookup::find($provider, $fromCurrency[0], $toCurrency[0], $at)->rate;
            $factor = $factor->multipliedBy($fromCurrency[1] >= 0 ? $rate->power($fromCurrency[1]) : $rate->power(-$fromCurrency[1])->reciprocal());
        }

        return fn (BigRational $value) => $value->multipliedBy($factor);
    }

    /** Whether a value in one unit can be expressed in the other. */
    public static function compatible(Unit $a, Unit $b): bool
    {
        $vectorsMatch = CompoundUnit::vectorOf($a)->equals(CompoundUnit::vectorOf($b));

        if ($a->dimension() === $b->dimension()) {
            return $a->dimension() !== Dimension::Derived || $vectorsMatch;
        }

        // Named dimensions sharing a vector (energy vs torque) stay distinct unless one side is a compound.
        return ($a instanceof CompoundUnit || $b instanceof CompoundUnit) && $vectorsMatch;
    }

    /**
     * Parses freeform text, including an optional "to <target>" suffix.
     *
     * @throws ParseException
     *
     * @return \Unitary\Quantity<\Unitary\Unit>|Color|ZonedTime|Coordinate|Scale
     */
    public function parse(string $input): Quantity|Color|ZonedTime|Coordinate|Scale
    {
        $input = trim($input);

        if (($adjusted = Inflation::tryParse($input, fn (string $amount) => $this->parser->tryParse($amount))) !== null) {
            return $adjusted;
        }

        [$input, $at] = $this->extractDate($input);
        $incompatible = null;

        // Try each separator from the right, so "5 in in cm" splits at the last "in".
        if (preg_match_all(self::TARGET_SEPARATOR, $input, $separators, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach (array_reverse($separators) as [[$separator, $offset]]) {
                $source = trim(substr($input, 0, $offset));
                $target = trim(substr($input, $offset + strlen($separator)));

                if ($source === '' || $target === '') {
                    continue;
                }

                try {
                    $converted = $this->tryParseWithTarget($source, $target, $at);
                } catch (IncompatibleUnitsException $e) {
                    $incompatible ??= $e;

                    continue;
                }

                if ($converted !== null) {
                    return $converted;
                }
            }
        }

        return $this->parser->tryParse($input)
            ?? Distance::tryParse($input)
            ?? Coordinate::tryParse($input)
            ?? Color::tryParse($input)
            ?? ZonedTime::tryParse($input)
            ?? Scales::tryParse($input)
            ?? $this->tryCalculate($input)
            ?? throw $incompatible ?? ParseException::cannotParse($input, 'quantity, color, time, coordinate or scale');
    }

    /**
     * Arithmetic and percentages as a last resort: "50 + 4", "65% of 30", "(2 + 3) × 4".
     * Strict, so unknown words fail rather than being ignored as labels.
     *
     * @return Quantity<Unit>|Color|ZonedTime|Coordinate|Scale|null
     */
    private function tryCalculate(string $input): Quantity|Color|ZonedTime|Coordinate|Scale|null
    {
        if ($this->calculating) {
            return null;
        }

        $this->calculating = true;

        try {
            $result = (new Calculator($this, strict: true))->evaluate($input);
        } catch (UnitaryException|MathException) {
            return null;
        } finally {
            $this->calculating = false;
        }

        // The calculator adds decibels as plain numbers, which parse() refuses to do.
        if ($result instanceof Quantity && $result->unit() instanceof MeasurementUnit && $result->unit()->definition()->logBase !== null) {
            return null;
        }

        return $result;
    }

    /**
     * Like parse(), but returns null instead of throwing.
     *
     * @return Quantity<Unit>|Color|ZonedTime|Coordinate|Scale|null
     */
    public function tryParse(string $input): Quantity|Color|ZonedTime|Coordinate|Scale|null
    {
        try {
            return $this->parse($input);
        } catch (UnitaryException) {
            return null;
        }
    }

    /**
     * Whether the input means something: a conversion, quantity, color, time,
     * coordinate, scale or calculation that can actually be answered. Gibberish,
     * unknown units and impossible conversions ("5 kg to m") are false.
     *
     * Runs offline: currencies are checked against placeholder rates, so this
     * never makes network requests, even when a live provider is configured.
     *
     * Calculations must be made of known words unless $allowLabels is true, so
     * "asdf 5 qwer" isn't mistaken for 5; with it, "3 pizzas at $12 each" passes.
     */
    public function understands(string $input, bool $allowLabels = false): bool
    {
        if (trim($input) === '') {
            return false;
        }

        $offline = $this->offline();

        try {
            $offline->parse($input);

            return true;
        } catch (UnitaryException) {
        }

        try {
            return (new Calculator($offline, strict: ! $allowLabels))->evaluate($input) !== null;
        } catch (UnitaryException|MathException) {
            return false;
        }
    }

    /**
     * A copy of this converter (same units) whose exchange rates are all 1, for
     * validating input and checking types without touching the network.
     */
    public function offline(): self
    {
        $rates = [];

        foreach (Currency::cases() as $currency) {
            $rates[$currency->value] = 1;
        }

        return new self($this->units, new FixedExchangeRates(Currency::USD, $rates));
    }

    /**
     * Parses freeform text that must be a quantity, e.g. "13' 11\"" or "£23".
     *
     * @return Quantity<Unit>
     *
     * @throws ParseException
     */
    public function parseQuantity(string $input, ?Dimension $prefer = null): Quantity
    {
        return $this->parser->parse($input, $prefer);
    }

    /**
     * Splits off a trailing "on 2020-03-01" when it's a real date.
     *
     * @return array{string, ?DateTimeImmutable}
     */
    private function extractDate(string $input): array
    {
        if (! preg_match(self::DATE_SUFFIX, $input, $matches, PREG_OFFSET_CAPTURE)) {
            return [$input, null];
        }

        try {
            $date = new DateTimeImmutable($matches['date'][0]);
        } catch (Exception) {
            return [$input, null];
        }

        return [substr($input, 0, $matches[0][1]), $date];
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>|Color|ZonedTime|Coordinate|Scale|null
     */
    private function tryParseWithTarget(string $source, string $target, ?DateTimeInterface $at): Quantity|Color|ZonedTime|Coordinate|Scale|null
    {
        // "1 cup of flour in g", "1 gal of petrol in kg CO2"
        $substance = SubstanceConverter::tryParse($source);

        if ($substance !== null && ($amount = $this->parser->tryParse($substance['amount'])) !== null) {
            return SubstanceConverter::convert($amount->withConverter($this), $substance['substance'], $target, $this->units);
        }

        $quantity = $this->parser->tryParse($source);

        if ($quantity !== null) {
            // "$20/day + $300/week in /month" keeps the source's numerator.
            if (str_starts_with($target, '/') && $quantity->unit() instanceof CompoundUnit) {
                $target = $quantity->unit()->numeratorSymbol().$target;
            }

            $unit = $this->units->tryResolve($target, $quantity->dimension());

            if ($unit === null) {
                $scaled = Scales::tryConvert($source, $target);

                return $scaled ?? (($format = CoordinateFormat::tryFromName($target)) !== null ? Coordinate::tryParse($source)?->in($format) : null);
            }

            if (! self::compatible($quantity->unit(), $unit)) {
                // Let the target disambiguate the source: "23 pounds in dollars" means GBP, not lb.
                foreach ($this->units->candidates($target) as $candidate) {
                    $reread = $this->parser->tryParse($source, $candidate->dimension());

                    if ($reread !== null && self::compatible($reread->unit(), $candidate)) {
                        return $this->convertQuantity($reread, $candidate, $at);
                    }
                }

                // "12 AWG in mm" is a wire gauge, not 12 Aruban florins.
                if (($scaled = Scales::tryConvert($source, $target)) !== null) {
                    return $scaled;
                }
            }

            // Missing exchange rates or mismatched dimensions should fail loudly.
            return $this->convertQuantity($quantity, $unit, $at);
        }

        $distance = Distance::tryParse($source);

        if ($distance !== null) {
            return $this->convertQuantity($distance->withConverter($this), $target);
        }

        $coordinate = Coordinate::tryParse($source);

        if ($coordinate !== null) {
            $format = CoordinateFormat::tryFromName($target);

            return $format !== null ? $coordinate->in($format) : null;
        }

        $color = Color::tryParse($source);

        if ($color !== null) {
            try {
                return $color->to($target);
            } catch (ParseException) {
                return null;
            }
        }

        $scaled = Scales::tryConvert($source, $target);

        if ($scaled !== null) {
            return $scaled;
        }

        $zone = Timezone::tryResolve($target);

        return $zone !== null ? ZonedTime::tryParse($source)?->to($zone) : null;
    }

    /**
     * @return array{Currency, int}|null
     */
    private static function currencyOf(Unit $unit): ?array
    {
        return match (true) {
            $unit instanceof Currency => [$unit, 1],
            $unit instanceof CompoundUnit => $unit->currency(),
            default => null,
        };
    }
}
