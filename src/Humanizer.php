<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigRational;
use Unitary\Units\DigitalStorage;

/**
 * Picks readable units: the largest unit on a ladder that keeps the value at
 * least 1, or an SI prefix that keeps it between 1 and 1000.
 *
 * @internal
 */
final class Humanizer
{
    /** @var array<string, array<string, list<string>>> dimension => system => ascending unit symbols */
    private const LADDERS = [
        'length' => ['metric' => ['nm', 'µm', 'mm', 'cm', 'm', 'km'], 'imperial' => ['in', 'ft', 'mi'], 'us' => ['in', 'ft', 'mi']],
        'mass' => ['metric' => ['µg', 'mg', 'g', 'kg', 't'], 'imperial' => ['oz', 'lb', 'LT'], 'us' => ['oz', 'lb', 'ton']],
        'time' => ['*' => ['ns', 'µs', 'ms', 's', 'min', 'h', 'd', 'yr']],
        'volume' => ['metric' => ['µL', 'mL', 'L', 'm³'], 'imperial' => ['imp fl oz', 'imp pt', 'imp gal'], 'us' => ['tsp', 'tbsp', 'fl oz', 'cup', 'qt', 'gal']],
        'area' => ['metric' => ['mm²', 'cm²', 'm²', 'ha', 'km²'], 'imperial' => ['in²', 'ft²', 'ac', 'mi²'], 'us' => ['in²', 'ft²', 'ac', 'mi²']],
        'digital_storage' => ['*' => ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB']],
        'data_rate' => ['*' => ['bit/s', 'kbit/s', 'Mbit/s', 'Gbit/s', 'Tbit/s']],
        'speed' => ['metric' => ['km/h'], 'imperial' => ['mph'], 'us' => ['mph']],
        'temperature' => ['metric' => ['°C'], 'imperial' => ['°F'], 'us' => ['°F']],
        'temperature_difference' => ['metric' => ['Δ°C'], 'imperial' => ['Δ°F'], 'us' => ['Δ°F']],
        'pressure' => ['imperial' => ['psi'], 'us' => ['psi']],
        'force' => ['imperial' => ['lbf'], 'us' => ['lbf']],
        'torque' => ['metric' => ['N⋅m'], 'imperial' => ['lbf⋅ft'], 'us' => ['lbf⋅ft']],
        'fuel_economy' => ['metric' => ['L/100km'], 'imperial' => ['mpg (imp)'], 'us' => ['mpg']],
        'density' => ['metric' => ['kg/m³'], 'imperial' => ['lb/ft³'], 'us' => ['lb/ft³']],
        'volumetric_flow_rate' => ['metric' => ['L/s'], 'imperial' => ['ft³/min'], 'us' => ['gal/min']],
        'energy' => ['imperial' => ['BTU'], 'us' => ['BTU']],
        'power' => ['imperial' => ['hp'], 'us' => ['hp']],
        'fantasy_currency' => ['*' => ['cp', 'sp', 'gp', 'pp']],
    ];

    private const BINARY_STORAGE = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];

    /**
     * @param  Quantity<Unit>  $quantity
     * @return Quantity<Unit>
     */
    public static function humanize(Quantity $quantity, ?UnitSystem $system = null): Quantity
    {
        $unit = $quantity->unit();
        $system ??= UnitSystem::of($unit) ?? UnitSystem::Metric;
        $ladders = self::LADDERS[$unit->dimension()->value] ?? [];
        $ladder = $ladders['*'] ?? $ladders[$system->value] ?? null;

        if ($unit->dimension() === Dimension::DigitalStorage && in_array($unit, [DigitalStorage::Kibibyte, DigitalStorage::Mebibyte, DigitalStorage::Gibibyte, DigitalStorage::Tebibyte, DigitalStorage::Pebibyte], true)) {
            $ladder = self::BINARY_STORAGE;
        }

        if ($ladder !== null) {
            return self::pick($quantity, $ladder);
        }

        return self::withSiPrefix($quantity);
    }

    /**
     * @param  Quantity<Unit>  $quantity
     * @param  list<string>  $ladder
     * @return Quantity<Unit>
     */
    private static function pick(Quantity $quantity, array $ladder): Quantity
    {
        $best = null;

        foreach ($ladder as $symbol) {
            $candidate = $quantity->to($symbol);

            if ($best === null || $candidate->value()->abs()->isGreaterThanOrEqualTo(1)) {
                $best = $candidate;
            }
        }

        return $best ?? $quantity;
    }

    /**
     * kW, MPa, GHz...: keeps the value between 1 and 1000 using engineering prefixes.
     *
     * @param  Quantity<Unit>  $quantity
     * @return Quantity<Unit>
     */
    private static function withSiPrefix(Quantity $quantity): Quantity
    {
        $registry = UnitRegistry::default();
        $base = $registry->coherent($quantity->unit());

        if (! $base instanceof MeasurementUnit || ! $base->definition()->siPrefixes || $quantity->isZero()) {
            return $quantity;
        }

        $inBase = $quantity->to($base);
        $best = $inBase;

        foreach (Prefix::cases() as $prefix) {
            if ($prefix->isBinary() || $prefix->exponent() % 3 !== 0 || abs($prefix->exponent()) > 24) {
                continue;
            }

            $value = $inBase->value()->abs()->dividedBy($prefix->factor());

            if ($value->isGreaterThanOrEqualTo(1) && $value->isLessThan(BigRational::of(1000))) {
                $best = $inBase->to($registry->resolve($prefix->symbol().$base->symbol(), $base->dimension()));
            }
        }

        return $best;
    }
}
