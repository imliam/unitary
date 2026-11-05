<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: kilometer per liter. Liters per 100 km is a reciprocal scale (bigger = thirstier).
 */
enum FuelEconomy: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case KilometerPerLiter = 'km/L';
    case LiterPer100Kilometers = 'L/100km';
    case MilePerGallon = 'mpg';
    case MilePerImperialGallon = 'mpg (imp)';
    case MilePerLiter = 'mi/L';

    public function dimension(): Dimension
    {
        return Dimension::FuelEconomy;
    }

    protected function define(): U
    {
        return match ($this) {
            self::KilometerPerLiter => U::of('kilometer per liter')->aliases('km/l', 'kmpl', 'kpl'),
            self::LiterPer100Kilometers => U::of('liter per 100 kilometers', 100)->reciprocal()->aliases('l/100km', 'L/100 km', 'l/100 km', 'litres per 100km')->plural('liters per 100 kilometers'),
            self::MilePerGallon => U::of('mile per gallon', '1.609344 / 3.785411784')->aliases('MPG', 'mpg US', 'US mpg', 'mi/gal', 'miles per US gallon'),
            self::MilePerImperialGallon => U::of('mile per imperial gallon', '1.609344 / 4.54609')->aliases('mpg UK', 'UK mpg', 'mpg imp', 'imperial mpg', 'miles per UK gallon'),
            self::MilePerLiter => U::of('mile per liter', '1.609344')->aliases('mi/l'),
        };
    }
}
