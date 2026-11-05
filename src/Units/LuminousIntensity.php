<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: candela.
 */
enum LuminousIntensity: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Candela = 'cd';
    case Candlepower = 'cp';

    public function dimension(): Dimension
    {
        return Dimension::LuminousIntensity;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Candela => U::of('candela')->prefixable(),
            self::Candlepower => U::of('candlepower', '0.981')->plural('candlepower'),
        };
    }
}
