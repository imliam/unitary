<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: kilogram (the gram is the prefixable unit).
 */
enum Mass: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Kilogram = 'kg';
    case Gram = 'g';
    case Milligram = 'mg';
    case Microgram = 'µg';
    case MetricTon = 't';
    case Ounce = 'oz';
    case Pound = 'lb';
    case Stone = 'st';
    case ShortTon = 'ton';
    case LongTon = 'LT';
    case ShortHundredweight = 'sh cwt';
    case LongHundredweight = 'cwt';
    case Grain = 'gr';
    case Dram = 'dr';
    case TroyOunce = 'ozt';
    case TroyPound = 'lbt';
    case Pennyweight = 'dwt';
    case Carat = 'ct';
    case Fun = 'fun (JP)';
    case EarthMass = 'M⊕';
    case SolarMass = 'M☉';
    case Monme = 'monme';
    case Slug = 'slug';
    case Dalton = 'Da';
    case PlanckMass = 'mP';

    public function dimension(): Dimension
    {
        return Dimension::Mass;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Kilogram => U::of('kilogram', 1)->aliases('kilo', 'kilos', 'kgs'),
            self::Gram => U::of('gram', '0.001')->prefixable()->aliases('gm', 'gramme', 'grammes'),
            self::Milligram => U::of('milligram', '10^-6'),
            self::Microgram => U::of('microgram', '10^-9')->aliases('μg', 'ug', 'mcg'),
            self::MetricTon => U::of('metric ton', 1000)->aliases('tonne', 'tonnes', 'metric tonne', 'metric tonnes'),
            self::Ounce => U::of('ounce', '0.028349523125')->aliases('avoirdupois ounce'),
            self::Pound => U::of('pound', '0.45359237')->aliases('lbs', '#', 'avoirdupois pound'),
            self::Stone => U::of('stone', '6.35029318')->plural('stone', 'stones'),
            self::ShortTon => U::of('short ton', '907.18474')->aliases('US ton', 'US tons', 'tons', 'tn'),
            self::LongTon => U::of('long ton', '1016.0469088')->aliases('imperial ton', 'imperial tons', 'UK ton'),
            self::ShortHundredweight => U::of('short hundredweight', '45.359237')->aliases('US hundredweight'),
            self::LongHundredweight => U::of('hundredweight', '50.80234544')->aliases('long hundredweight', 'imperial hundredweight'),
            self::Grain => U::of('grain', '0.00006479891'),
            self::Dram => U::of('dram', '0.0017718451953125'),
            self::TroyOunce => U::of('troy ounce', '0.0311034768')->aliases('oz t', 'oz troy'),
            self::TroyPound => U::of('troy pound', '0.3732417216'),
            self::Pennyweight => U::of('pennyweight', '0.00155517384'),
            self::Carat => U::of('carat', '0.0002')->aliases('metric carat'),
            self::EarthMass => U::of('Earth mass', '5.9722e24')->aliases('Earth masses', 'M_earth', 'Mearth'),
            self::SolarMass => U::of('solar mass', '1.98847e30')->aliases('solar masses', 'M_sun', 'Msun'),
            self::Fun => U::of('Japanese fun', '0.000375')->plural('Japanese fun'),
            self::Monme => U::of('monme', '0.00375')->aliases('mommé', 'momme')->plural('monme'),
            self::Slug => U::of('slug', '0.45359237 * 9.80665 / 0.3048'),
            self::Dalton => U::of('dalton', '1.66053906892e-27')->aliases('u', 'amu', 'atomic mass unit'),
            self::PlanckMass => U::of('Planck mass', '2.176434e-8'),
        };
    }
}
