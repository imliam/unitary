<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: hertz.
 */
enum Frequency: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Hertz = 'Hz';
    case Kilohertz = 'kHz';
    case Megahertz = 'MHz';
    case Gigahertz = 'GHz';
    case RevolutionPerMinute = 'rpm';
    case BeatPerMinute = 'bpm';
    case RadianPerSecond = 'rad/s';

    public function dimension(): Dimension
    {
        return Dimension::Frequency;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Hertz => U::of('hertz')->prefixable()->aliases('hz', 'cps', 'cycles per second'),
            self::Kilohertz => U::of('kilohertz', 1000)->aliases('khz'),
            self::Megahertz => U::of('megahertz', '10^6')->aliases('mhz'),
            self::Gigahertz => U::of('gigahertz', '10^9')->aliases('ghz'),
            self::RevolutionPerMinute => U::of('revolution per minute', '1 / 60')->aliases('RPM', 'r/min', 'rev/min'),
            self::BeatPerMinute => U::of('beat per minute', '1 / 60')->aliases('BPM'),
            self::RadianPerSecond => U::of('radian per second', '1 / 2 / pi')->aliases('rad s-1'),
        };
    }
}
