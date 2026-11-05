<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: farad.
 */
enum Capacitance: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Farad = 'F';
    case Microfarad = 'µF';
    case Nanofarad = 'nF';
    case Picofarad = 'pF';

    public function dimension(): Dimension
    {
        return Dimension::Capacitance;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Farad => U::of('farad')->prefixable(),
            self::Microfarad => U::of('microfarad', '10^-6')->aliases('μF', 'uF', 'mfd'),
            self::Nanofarad => U::of('nanofarad', '10^-9'),
            self::Picofarad => U::of('picofarad', '10^-12')->aliases('puff', 'puffs'),
        };
    }
}
