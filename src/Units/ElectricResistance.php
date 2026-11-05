<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: ohm.
 */
enum ElectricResistance: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Ohm = 'Ω';
    case Kiloohm = 'kΩ';
    case Megaohm = 'MΩ';

    public function dimension(): Dimension
    {
        return Dimension::ElectricResistance;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Ohm => U::of('ohm')->prefixable()->aliases('Ω'),
            self::Kiloohm => U::of('kiloohm', 1000)->aliases('kohm', 'kΩ', 'kilohm', 'kilohms'),
            self::Megaohm => U::of('megaohm', '10^6')->aliases('Mohm', 'MΩ', 'megohm', 'megohms'),
        };
    }
}
