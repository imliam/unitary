<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: siemens.
 */
enum ElectricConductance: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Siemens = 'S';

    public function dimension(): Dimension
    {
        return Dimension::ElectricConductance;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Siemens => U::of('siemens')->prefixable()->aliases('mho', 'mhos', '℧'),
        };
    }
}
