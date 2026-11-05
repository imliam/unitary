<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: watt.
 */
enum Power: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Watt = 'W';
    case Kilowatt = 'kW';
    case Megawatt = 'MW';
    case Gigawatt = 'GW';
    case Horsepower = 'hp';
    case MetricHorsepower = 'PS';
    case ElectricalHorsepower = 'hp(E)';
    case BtuPerHour = 'BTU/h';
    case ErgPerSecond = 'erg/s';
    case FootPoundPerSecond = 'ft⋅lbf/s';
    case TonOfRefrigeration = 'TR';
    case DecibelMilliwatt = 'dBm';
    case DecibelWatt = 'dBW';
    case SolarLuminosity = 'L☉';

    public function dimension(): Dimension
    {
        return Dimension::Power;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Watt => U::of('watt')->prefixable(),
            self::Kilowatt => U::of('kilowatt', 1000),
            self::Megawatt => U::of('megawatt', '10^6'),
            self::Gigawatt => U::of('gigawatt', '10^9'),
            self::Horsepower => U::of('horsepower', '550 * 0.3048 * 4.4482216152605')->aliases('mechanical horsepower', 'imperial horsepower', 'bhp')->plural('horsepower'),
            self::MetricHorsepower => U::of('metric horsepower', '735.49875')->aliases('cv', 'CV', 'pk')->plural('metric horsepower'),
            self::ElectricalHorsepower => U::of('electrical horsepower', 746)->plural('electrical horsepower'),
            self::BtuPerHour => U::of('BTU per hour', '1055.05585262 / 3600')->aliases('Btu/h', 'BTU/hr', 'btu/h', 'BTUH'),
            self::ErgPerSecond => U::of('erg per second', '10^-7'),
            self::FootPoundPerSecond => U::of('foot-pound per second', '0.3048 * 4.4482216152605')->aliases('ft-lbf/s', 'ft lbf/s'),
            self::DecibelMilliwatt => U::of('decibel-milliwatt', '0.001')->logarithmic('10', 10)->aliases('dBmW', 'decibel milliwatt', 'decibel milliwatts'),
            self::DecibelWatt => U::of('decibel-watt', 1)->logarithmic('10', 10)->aliases('decibel watt', 'decibel watts'),
            // IAU 2015 nominal solar luminosity.
            self::SolarLuminosity => U::of('solar luminosity', '3.828e26')->aliases('L_sun', 'Lsun')->plural('solar luminosities'),
            self::TonOfRefrigeration => U::of('ton of refrigeration', '12000 * 1055.05585262 / 3600')->aliases('RT', 'tons of refrigeration'),
        };
    }
}
