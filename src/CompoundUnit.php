<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigRational;
use Unitary\Algebra\DimensionVector;
use Unitary\Algebra\Superscript;
use Unitary\Currency\Currency;
use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Units\Ratio;

/**
 * A product of units raised to integer powers, e.g. kg⋅m/s², $/h or m⋅ft.
 *
 * Built by multiplying or dividing quantities, or by parsing unit text the
 * registry doesn't know as a whole. Affine units contribute only their scale
 * (°C/min is a rate of change), and reciprocal and logarithmic units can't take part.
 */
final readonly class CompoundUnit implements Unit
{
    /** @var list<array{unit: Unit, power: int}> */
    public array $factors;

    private DimensionVector $vector;

    /**
     * @param  list<array{unit: Unit, power: int}>  $factors
     */
    public function __construct(array $factors)
    {
        $merged = [];

        foreach ($factors as $factor) {
            foreach ($factor['unit'] instanceof self ? $factor['unit']->factors : [$factor] as $inner) {
                $power = $inner['power'] * ($factor['unit'] instanceof self ? $factor['power'] : 1);
                $key = self::keyOf($inner['unit']);

                if ($inner['unit'] === Ratio::One) {
                    continue;
                }

                if ($inner['unit'] instanceof MeasurementUnit && ($inner['unit']->definition()->reciprocal || $inner['unit']->definition()->logBase !== null)) {
                    throw new IncompatibleUnitsException(sprintf('Reciprocal and logarithmic units such as %s cannot be combined with other units', $inner['unit']->symbol()));
                }

                $merged[$key] = ['unit' => $inner['unit'], 'power' => ($merged[$key]['power'] ?? 0) + $power];
            }
        }

        $this->factors = array_values(array_filter($merged, fn (array $factor) => $factor['power'] !== 0));
        $this->vector = array_reduce(
            $this->factors,
            fn (DimensionVector $vector, array $factor) => $vector->multipliedBy(self::vectorOf($factor['unit'])->power($factor['power'])),
            DimensionVector::none(),
        );
    }

    public static function product(Unit $a, Unit $b): self
    {
        return new self([['unit' => $a, 'power' => 1], ['unit' => $b, 'power' => 1]]);
    }

    public static function quotient(Unit $a, Unit $b): self
    {
        return new self([['unit' => $a, 'power' => 1], ['unit' => $b, 'power' => -1]]);
    }

    public static function power(Unit $unit, int $power): self
    {
        return new self([['unit' => $unit, 'power' => $power]]);
    }

    public function vector(): DimensionVector
    {
        return $this->vector;
    }

    public function dimension(): Dimension
    {
        return Dimension::fromVector($this->vector);
    }

    public function isEmpty(): bool
    {
        return $this->factors === [];
    }

    public function symbol(): string
    {
        [$numerator, $denominator] = $this->split();
        $top = $numerator === [] ? '1' : self::join($numerator);

        if ($denominator === []) {
            return $top;
        }

        return $top.'/'.(count($denominator) > 1 ? '('.self::join($denominator).')' : self::join($denominator));
    }

    /** The symbol of the positive-power part, e.g. "USD" for USD/h. */
    public function numeratorSymbol(): string
    {
        $numerator = $this->split()[0];

        return $numerator === [] ? '' : self::join($numerator);
    }

    public function name(): string
    {
        [$numerator, $denominator] = $this->split();
        $name = implode(' ', array_map(self::describe(...), $numerator));

        return trim($name.($denominator === [] ? '' : ' per '.implode(' ', array_map(self::describe(...), $denominator))));
    }

    public function aliases(): array
    {
        return [];
    }

    /**
     * How many coherent SI units one of this unit is, ignoring currencies.
     */
    public function coherentFactor(): BigRational
    {
        $factor = BigRational::one();

        foreach ($this->factors as ['unit' => $unit, 'power' => $power]) {
            $unitFactor = self::coherentFactorOf($unit);
            $factor = $factor->multipliedBy($power >= 0 ? $unitFactor->power($power) : $unitFactor->power(-$power)->reciprocal());
        }

        return $factor;
    }

    /**
     * The currency this unit is denominated in, and its power (1 for $/h).
     *
     * @return array{Currency, int}|null
     *
     * @throws IncompatibleUnitsException when more than one currency is involved
     */
    public function currency(): ?array
    {
        $currencies = array_values(array_filter($this->factors, fn (array $f) => $f['unit'] instanceof Currency));

        if (count($currencies) > 1) {
            throw new IncompatibleUnitsException('Units mixing several currencies cannot be converted');
        }

        if ($currencies === []) {
            return null;
        }

        /** @var Currency $currency */
        $currency = $currencies[0]['unit'];

        return [$currency, $currencies[0]['power']];
    }

    public static function vectorOf(Unit $unit): DimensionVector
    {
        if ($unit instanceof self) {
            return $unit->vector;
        }

        return $unit->dimension()->vector() ?? DimensionVector::none();
    }

    public static function coherentFactorOf(Unit $unit): BigRational
    {
        return match (true) {
            $unit instanceof self => $unit->coherentFactor(),
            $unit instanceof MeasurementUnit => $unit->definition()->factor->multipliedBy($unit->dimension()->coherentFactor()),
            default => BigRational::one(),
        };
    }

    /**
     * The positive and (negated) negative powers: kg⋅m/s² → [[kg, m], [s²]].
     *
     * @return array{list<array{unit: Unit, power: int}>, list<array{unit: Unit, power: int}>}
     */
    private function split(): array
    {
        $numerator = [];
        $denominator = [];

        foreach ($this->factors as $factor) {
            if ($factor['power'] > 0) {
                $numerator[] = $factor;
            } else {
                $denominator[] = ['unit' => $factor['unit'], 'power' => -$factor['power']];
            }
        }

        return [$numerator, $denominator];
    }

    /**
     * @param  list<array{unit: Unit, power: int}>  $factors
     */
    private static function join(array $factors): string
    {
        return implode('⋅', array_map(
            fn (array $factor) => self::wrap($factor['unit']->symbol()).($factor['power'] === 1 ? '' : Superscript::of($factor['power'])),
            $factors,
        ));
    }

    /**
     * @param  array{unit: Unit, power: int}  $factor
     */
    private static function describe(array $factor): string
    {
        return match ($factor['power']) {
            1 => $factor['unit']->name(),
            2 => 'square '.$factor['unit']->name(),
            3 => 'cubic '.$factor['unit']->name(),
            default => $factor['unit']->name().' to the power '.$factor['power'],
        };
    }

    private static function keyOf(Unit $unit): string
    {
        return $unit->dimension()->value.':'.$unit->symbol();
    }

    private static function wrap(string $symbol): string
    {
        return preg_match('/[\/⋅ ]/u', $symbol) ? "({$symbol})" : $symbol;
    }
}
