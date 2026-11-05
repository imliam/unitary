<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: weber.
 */
enum MagneticFlux: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Weber = 'Wb';
    case Maxwell = 'Mx';

    public function dimension(): Dimension
    {
        return Dimension::MagneticFlux;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Weber => U::of('weber')->prefixable(),
            self::Maxwell => U::of('maxwell', '10^-8'),
        };
    }
}
