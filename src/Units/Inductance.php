<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: henry.
 */
enum Inductance: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Henry = 'H';

    public function dimension(): Dimension
    {
        return Dimension::Inductance;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Henry => U::of('henry')->prefixable()->plural('henries', 'henrys'),
        };
    }
}
