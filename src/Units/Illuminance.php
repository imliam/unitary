<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: lux.
 */
enum Illuminance: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Lux = 'lx';
    case FootCandle = 'fc';
    case Phot = 'ph';

    public function dimension(): Dimension
    {
        return Dimension::Illuminance;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Lux => U::of('lux')->prefixable()->plural('lux', 'luxes'),
            self::FootCandle => U::of('foot-candle', '1 / 0.09290304')->aliases('foot candle', 'foot candles', 'ft-c', 'lm/ft²'),
            self::Phot => U::of('phot', 10000),
        };
    }
}
