<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: square meter.
 */
enum Area: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case SquareMeter = 'm²';
    case SquareKilometer = 'km²';
    case SquareCentimeter = 'cm²';
    case SquareMillimeter = 'mm²';
    case Hectare = 'ha';
    case Are = 'are';
    case Acre = 'ac';
    case Rood = 'rood';
    case SquareInch = 'in²';
    case SquareFoot = 'ft²';
    case SquareYard = 'yd²';
    case SquareMile = 'mi²';
    case Dunam = 'dunam';
    case Tsubo = 'tsubo';
    case Se = 'se (JP)';
    case Cho = 'chō';
    case Barn = 'b';

    public function dimension(): Dimension
    {
        return Dimension::Area;
    }

    protected function define(): U
    {
        return match ($this) {
            self::SquareMeter => U::of('square meter')->aliases('m2', 'm^2', 'sq m', 'sqm', 'meter squared', 'meters squared'),
            self::SquareKilometer => U::of('square kilometer', '10^6')->aliases('km2', 'km^2', 'sq km', 'sqkm'),
            self::SquareCentimeter => U::of('square centimeter', '10^-4')->aliases('cm2', 'cm^2', 'sq cm'),
            self::SquareMillimeter => U::of('square millimeter', '10^-6')->aliases('mm2', 'mm^2', 'sq mm'),
            self::Hectare => U::of('hectare', 10000),
            self::Are => U::of('are', 100),
            self::Acre => U::of('acre', '4046.8564224'),
            self::Rood => U::of('rood', '1011.7141056'),
            self::SquareInch => U::of('square inch', '0.00064516')->aliases('in2', 'in^2', 'sq in', 'sq. in.'),
            self::SquareFoot => U::of('square foot', '0.09290304')->aliases('ft2', 'ft^2', 'sq ft', 'sqft', 'sq. ft.'),
            self::SquareYard => U::of('square yard', '0.83612736')->aliases('yd2', 'yd^2', 'sq yd'),
            self::SquareMile => U::of('square mile', '2589988.110336')->aliases('mi2', 'mi^2', 'sq mi'),
            self::Dunam => U::of('dunam', 1000)->aliases('dönüm', 'donum', 'dunum', 'metric dunam'),
            // Japanese areas are defined from the tsubo, 400/121 square metres.
            self::Tsubo => U::of('tsubo', '400 / 121')->plural('tsubo'),
            self::Se => U::of('Japanese se', '12000 / 121')->plural('Japanese se'),
            self::Cho => U::of('chō', '1200000 / 121')->aliases('cho', 'Japanese cho')->plural('chō'),
            self::Barn => U::of('barn', '10^-28')->prefixable(),
        };
    }
}
