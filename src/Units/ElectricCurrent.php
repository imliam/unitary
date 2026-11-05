<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: ampere.
 */
enum ElectricCurrent: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Ampere = 'A';
    case Milliampere = 'mA';
    case Kiloampere = 'kA';
    case Biot = 'Bi';

    public function dimension(): Dimension
    {
        return Dimension::ElectricCurrent;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Ampere => U::of('ampere')->prefixable()->aliases('amp', 'amps'),
            self::Milliampere => U::of('milliampere', '0.001')->aliases('milliamp', 'milliamps'),
            self::Kiloampere => U::of('kiloampere', 1000),
            self::Biot => U::of('biot', 10)->aliases('abampere', 'abamperes', 'abA'),
        };
    }
}
