<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: gray.
 */
enum AbsorbedDose: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Gray = 'Gy';
    case Rad = 'rad';

    public function dimension(): Dimension
    {
        return Dimension::AbsorbedDose;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Gray => U::of('gray')->prefixable(),
            self::Rad => U::of('rad', '0.01')->aliases('rads'),
        };
    }
}
