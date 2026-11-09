<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;
use Unitary\Units\Angle;

/**
 * A quantity split across descending units: 6 ft 2 in, 1 d 3 h 46 min 40 s, 51° 30′ 26″.
 */
final readonly class Breakdown implements JsonSerializable, Stringable
{
    /** @var array<string, array<string, list<string>>> */
    private const DEFAULTS = [
        'length' => ['metric' => ['km', 'm', 'cm', 'mm'], 'imperial' => ['mi', 'ft', 'in'], 'us' => ['mi', 'ft', 'in']],
        'mass' => ['metric' => ['t', 'kg', 'g'], 'imperial' => ['st', 'lb', 'oz'], 'us' => ['lb', 'oz']],
        'time' => ['*' => ['d', 'h', 'min', 's']],
        'volume' => ['metric' => ['L', 'mL'], 'imperial' => ['imp gal', 'imp pt', 'imp fl oz'], 'us' => ['gal', 'qt', 'pint', 'cup', 'fl oz']],
        'angle' => ['*' => ['°', 'arcmin', 'arcsec']],
        'fantasy_currency' => ['*' => ['pp', 'gp', 'sp', 'cp']],
    ];

    /**
     * @param  list<Quantity<Unit>>  $parts
     * @param  Quantity<Unit>  $total
     */
    private function __construct(
        public array $parts,
        public Quantity $total,
        private bool $negative,
    ) {
    }

    /**
     * @param  Quantity<Unit>  $quantity
     */
    public static function of(Quantity $quantity, Unit|string ...$units): self
    {
        $registry = UnitRegistry::default();
        $units = array_map(fn (Unit|string $unit) => $registry->resolve($unit, $quantity->dimension()), array_values($units));

        if ($units === []) {
            $defaults = self::DEFAULTS[$quantity->dimension()->value] ?? throw new InvalidArgumentException(sprintf('Pass the units to break %s down into', $quantity->dimension()->label()));
            $system = UnitSystem::of($quantity->unit()) ?? UnitSystem::Metric;
            $units = array_map(fn (string $symbol) => $registry->resolve($symbol, $quantity->dimension()), $defaults['*'] ?? $defaults[$system->value] ?? []);
        }

        foreach ($units as $unit) {
            if (! $unit instanceof MeasurementUnit || ! $unit->definition()->isLinear()) {
                throw new InvalidArgumentException(sprintf('Cannot break a quantity down into %s', $unit->symbol()));
            }
        }

        usort($units, fn (MeasurementUnit $a, MeasurementUnit $b) => $b->definition()->factor->compareTo($a->definition()->factor));

        $remaining = $quantity->abs();
        $parts = [];
        $last = count($units) - 1;

        foreach ($units as $index => $unit) {
            $inUnit = $remaining->to($unit);

            if ($index === $last) {
                $parts[] = $inUnit;

                break;
            }

            $whole = $inUnit->value()->toScale(0, RoundingMode::Down)->toBigRational();
            $parts[] = new Quantity($whole, $unit);
            $remaining = $inUnit->minus($whole);
        }

        return new self($parts, $quantity, $quantity->isNegative());
    }

    /**
     * Rounds the smallest part, carrying into larger ones (5 ft 11.99 in → 6 ft 0 in at 0 dp).
     *
     * @param  non-negative-int  $decimals
     */
    public function rounded(int $decimals = 0): self
    {
        $smallest = $this->parts[count($this->parts) - 1] ?? null;

        if ($smallest === null) {
            return $this;
        }

        $total = $this->total->to($smallest->unit());
        $rounded = new Quantity($total->value()->toScale($decimals, RoundingMode::HalfUp)->toBigRational(), $smallest->unit());

        return self::of($rounded, ...array_map(fn (Quantity $part) => $part->unit(), $this->parts));
    }

    /**
     * @return list<Quantity<Unit>>
     */
    public function parts(): array
    {
        return $this->parts;
    }

    /**
     * "5 ft 11 in". With a locale or display (see Quantity::format()), each part
     * is written that way: "5 pieds 11 pouces" for fr and UnitDisplay::Long.
     *
     * @param  non-negative-int|null  $decimals
     */
    public function toString(?int $decimals = null, ?string $locale = null, UnitDisplay|string|null $display = null): string
    {
        $breakdown = $decimals === null ? $this : $this->rounded($decimals);
        $nonZero = array_values(array_filter($breakdown->parts, fn (Quantity $part) => ! $part->isZero()));
        $parts = $nonZero === [] ? array_slice($breakdown->parts, -1) : $nonZero;
        $smallest = $breakdown->parts[count($breakdown->parts) - 1] ?? null;

        $text = implode(' ', array_map(fn (Quantity $part) => match (true) {
            $locale !== null || $display !== null => $part->format($part === $smallest ? $decimals : 0, locale: $locale, display: $display),
            $part->unit() === Angle::Arcminute => $part->toDecimal().'′',
            $part->unit() === Angle::Arcsecond => $part->toDecimal().'″',
            default => $part->toString(),
        }, $parts));

        return ($this->negative ? '-' : '').$text;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return list<array{value: string, unit: string, unitName: string, dimension: string, uncertainty?: string}>
     */
    public function jsonSerialize(): array
    {
        return array_map(fn (Quantity $part) => $part->jsonSerialize(), $this->parts);
    }

    /**
     * The value of each part, keyed by unit symbol.
     *
     * @return array<string, BigRational>
     */
    public function toArray(): array
    {
        $values = [];

        foreach ($this->parts as $part) {
            $values[$part->unit()->symbol()] = $part->value()->multipliedBy($this->negative ? -1 : 1);
        }

        return $values;
    }
}
