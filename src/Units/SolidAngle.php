<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: steradian.
 */
enum SolidAngle: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Steradian = 'sr';
    case SquareDegree = 'deg²';
    case Spat = 'sp';

    public function dimension(): Dimension
    {
        return Dimension::SolidAngle;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Steradian => U::of('steradian')->prefixable(),
            self::SquareDegree => U::of('square degree', 'pi / 180 * pi / 180')->aliases('deg2', 'sq deg', '°²'),
            self::Spat => U::of('spat', '4 * pi'),
        };
    }
}
