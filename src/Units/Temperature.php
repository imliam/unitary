<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: kelvin. Conversions are affine (they include an offset).
 */
enum Temperature: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Kelvin = 'K';
    case Celsius = '°C';
    case Fahrenheit = '°F';
    case Rankine = '°R';
    case Reaumur = '°Ré';
    case Delisle = '°De';
    case Newton = '°N';
    case Romer = '°Rø';

    public function dimension(): Dimension
    {
        return Dimension::Temperature;
    }

    /** The unit for a change measured on this scale: °C → Δ°C. */
    public function difference(): TemperatureDifference
    {
        return TemperatureDifference::from('Δ'.$this->value);
    }

    protected function define(): U
    {
        return match ($this) {
            self::Kelvin => U::of('kelvin')->prefixable()->aliases('°K', 'kelvins', 'degrees kelvin'),
            self::Celsius => U::of('celsius', 1, '273.15')->aliases('C', '℃', 'degC', 'deg C', 'degree celsius', 'degrees celsius', 'centigrade', 'degrees centigrade'),
            self::Fahrenheit => U::of('fahrenheit', '5 / 9', '45967 / 180')->aliases('F', '℉', 'degF', 'deg F', 'degree fahrenheit', 'degrees fahrenheit'),
            self::Rankine => U::of('rankine', '5 / 9')->aliases('°Ra', 'Ra', 'degR', 'degree rankine', 'degrees rankine'),
            self::Reaumur => U::of('réaumur', '5 / 4', '273.15')->aliases('°Re', 'Ré', 'reaumur', 'degrees réaumur', 'degrees reaumur'),
            self::Delisle => U::of('delisle', '-2 / 3', '373.15')->aliases('De', 'degrees delisle'),
            self::Newton => U::of('newton scale', '100 / 33', '273.15')->aliases('degrees newton'),
            self::Romer => U::of('rømer', '40 / 21', '1812.05 / 7')->aliases('Rø', 'romer', 'degrees rømer', 'degrees romer'),
        };
    }
}
