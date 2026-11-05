<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: meter per second squared.
 */
enum Acceleration: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case MeterPerSecondSquared = 'm/s²';
    case StandardGravity = 'g₀';
    case Gal = 'Gal';
    case FootPerSecondSquared = 'ft/s²';

    public function dimension(): Dimension
    {
        return Dimension::Acceleration;
    }

    protected function define(): U
    {
        return match ($this) {
            self::MeterPerSecondSquared => U::of('meter per second squared')->aliases('m/s2', 'm/s^2', 'm s-2'),
            self::StandardGravity => U::of('standard gravity', '9.80665')->aliases('g0', 'gn', 'gee', 'gees', 'g-force'),
            self::Gal => U::of('gal', '0.01')->prefixable()->aliases('galileo', 'galileos'),
            self::FootPerSecondSquared => U::of('foot per second squared', '0.3048')->aliases('ft/s2', 'ft/s^2'),
        };
    }
}
