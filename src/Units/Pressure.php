<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: pascal.
 */
enum Pressure: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Pascal = 'Pa';
    case Hectopascal = 'hPa';
    case Kilopascal = 'kPa';
    case Megapascal = 'MPa';
    case Bar = 'bar';
    case Millibar = 'mbar';
    case Atmosphere = 'atm';
    case TechnicalAtmosphere = 'at';
    case Torr = 'Torr';
    case MillimeterOfMercury = 'mmHg';
    case InchOfMercury = 'inHg';
    case CentimeterOfWater = 'cmH₂O';
    case InchOfWater = 'inH₂O';
    case Psi = 'psi';
    case Ksi = 'ksi';
    case DecibelSoundPressure = 'dB SPL';

    public function dimension(): Dimension
    {
        return Dimension::Pressure;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Pascal => U::of('pascal')->prefixable(),
            self::Hectopascal => U::of('hectopascal', 100),
            self::Kilopascal => U::of('kilopascal', 1000),
            self::Megapascal => U::of('megapascal', '10^6'),
            self::Bar => U::of('bar', 100000)->prefixable(),
            self::Millibar => U::of('millibar', 100)->aliases('mb'),
            self::Atmosphere => U::of('atmosphere', 101325)->aliases('standard atmosphere'),
            self::TechnicalAtmosphere => U::of('technical atmosphere', '98066.5')->aliases('kgf/cm²', 'kgf/cm2'),
            self::Torr => U::of('torr', '101325 / 760'),
            self::MillimeterOfMercury => U::of('millimeter of mercury', '133.322387415')->aliases('mm Hg'),
            self::InchOfMercury => U::of('inch of mercury', '3386.389')->aliases('in Hg', '"Hg'),
            self::CentimeterOfWater => U::of('centimeter of water', '98.0665')->aliases('cmH2O', 'cm H2O'),
            self::InchOfWater => U::of('inch of water', '249.08891')->aliases('inH2O', 'in H2O'),
            self::Psi => U::of('pound per square inch', '4.4482216152605 / 0.00064516')->aliases('lbf/in²', 'lbf/in2', 'lb/in²', 'lb/in2'),
            // Sound pressure level, referenced to 20 µPa (the threshold of hearing).
            self::DecibelSoundPressure => U::of('decibel sound pressure level', '0.00002')->logarithmic('10', 20)->aliases('dBSPL', 'dB(SPL)', 'dB(spl)'),
            self::Ksi => U::of('kilopound per square inch', '4448.2216152605 / 0.00064516'),
        };
    }
}
