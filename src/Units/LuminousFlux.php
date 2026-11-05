<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: lumen.
 */
enum LuminousFlux: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Lumen = 'lm';

    public function dimension(): Dimension
    {
        return Dimension::LuminousFlux;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Lumen => U::of('lumen')->prefixable(),
        };
    }
}
