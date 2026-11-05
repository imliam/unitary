<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: volt.
 */
enum Voltage: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Volt = 'V';
    case Millivolt = 'mV';
    case Kilovolt = 'kV';
    case DecibelVolt = 'dBV';
    case DecibelUnloaded = 'dBu';

    public function dimension(): Dimension
    {
        return Dimension::Voltage;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Volt => U::of('volt')->prefixable()->aliases('v'),
            self::Millivolt => U::of('millivolt', '0.001'),
            self::Kilovolt => U::of('kilovolt', 1000),
            self::DecibelVolt => U::of('decibel-volt', 1)->logarithmic('10', 20)->aliases('decibel volt', 'decibel volts'),
            // Referenced to √0.6 V, the voltage that dissipates 1 mW in 600 Ω.
            self::DecibelUnloaded => U::of('decibel-unloaded', '0.77459666924148337703585307995647992216658434105831')->logarithmic('10', 20)->aliases('decibel unloaded'),
        };
    }
}
