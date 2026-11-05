<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: kilogram per cubic meter.
 */
enum Density: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case KilogramPerCubicMeter = 'kg/m³';
    case GramPerCubicCentimeter = 'g/cm³';
    case GramPerMilliliter = 'g/mL';
    case KilogramPerLiter = 'kg/L';
    case GramPerLiter = 'g/L';
    case MilligramPerLiter = 'mg/L';
    case PoundPerCubicFoot = 'lb/ft³';
    case PoundPerCubicInch = 'lb/in³';
    case PoundPerGallon = 'lb/gal';

    public function dimension(): Dimension
    {
        return Dimension::Density;
    }

    protected function define(): U
    {
        return match ($this) {
            self::KilogramPerCubicMeter => U::of('kilogram per cubic meter')->aliases('kg/m3', 'kg/m^3', 'kg m-3'),
            self::GramPerCubicCentimeter => U::of('gram per cubic centimeter', 1000)->aliases('g/cm3', 'g/cc', 'g/cm^3'),
            self::GramPerMilliliter => U::of('gram per milliliter', 1000)->aliases('g/ml'),
            self::KilogramPerLiter => U::of('kilogram per liter', 1000)->aliases('kg/l'),
            self::GramPerLiter => U::of('gram per liter', 1)->aliases('g/l'),
            self::MilligramPerLiter => U::of('milligram per liter', '0.001')->aliases('mg/l'),
            self::PoundPerCubicFoot => U::of('pound per cubic foot', '0.45359237 / 0.028316846592')->aliases('lb/ft3', 'pcf', 'lb/cu ft'),
            self::PoundPerCubicInch => U::of('pound per cubic inch', '0.45359237 / 0.000016387064')->aliases('lb/in3', 'lb/cu in'),
            self::PoundPerGallon => U::of('pound per gallon', '0.45359237 / 0.003785411784')->aliases('ppg', 'lb/US gal'),
        };
    }
}
