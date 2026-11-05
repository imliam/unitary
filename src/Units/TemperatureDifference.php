<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: kelvin. A change in temperature rather than a reading, so there's
 * no offset: a rise of 20 Δ°C is a rise of 36 Δ°F, while 20 °C is 68 °F.
 * Subtracting two temperatures gives one of these.
 */
enum TemperatureDifference: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Kelvin = 'ΔK';
    case Celsius = 'Δ°C';
    case Fahrenheit = 'Δ°F';
    case Rankine = 'Δ°R';
    case Reaumur = 'Δ°Ré';
    case Delisle = 'Δ°De';
    case Newton = 'Δ°N';
    case Romer = 'Δ°Rø';

    public function dimension(): Dimension
    {
        return Dimension::TemperatureDifference;
    }

    /** The temperature scale this difference is measured on. */
    public function absolute(): Temperature
    {
        return Temperature::from(substr($this->value, strlen('Δ')));
    }

    protected function define(): U
    {
        return match ($this) {
            self::Kelvin => U::of('kelvin difference', 1)->aliases('delta K', 'kelvin change'),
            self::Celsius => U::of('celsius difference', 1)->aliases('ΔC', 'delta °C', 'delta C', 'C°', 'celsius degree', 'celsius degrees', 'degrees celsius difference'),
            self::Fahrenheit => U::of('fahrenheit difference', '5 / 9')->aliases('ΔF', 'delta °F', 'delta F', 'F°', 'fahrenheit degree', 'fahrenheit degrees', 'degrees fahrenheit difference'),
            self::Rankine => U::of('rankine difference', '5 / 9')->aliases('delta °R', 'delta R'),
            self::Reaumur => U::of('réaumur difference', '5 / 4')->aliases('delta °Ré'),
            self::Delisle => U::of('delisle difference', '-2 / 3')->aliases('delta °De'),
            self::Newton => U::of('newton scale difference', '100 / 33')->aliases('delta °N'),
            self::Romer => U::of('rømer difference', '40 / 21')->aliases('delta °Rø'),
        };
    }
}
