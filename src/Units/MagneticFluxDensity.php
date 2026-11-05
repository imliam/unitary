<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: tesla.
 */
enum MagneticFluxDensity: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Tesla = 'T';
    case Gauss = 'G';

    public function dimension(): Dimension
    {
        return Dimension::MagneticFluxDensity;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Tesla => U::of('tesla')->prefixable(),
            self::Gauss => U::of('gauss', '10^-4')->prefixable()->plural('gauss'),
        };
    }
}
