<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: meter per second.
 */
enum Speed: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case MeterPerSecond = 'm/s';
    case KilometerPerHour = 'km/h';
    case MilePerHour = 'mph';
    case Knot = 'kn';
    case FootPerSecond = 'ft/s';
    case InchPerSecond = 'in/s';
    case SpeedOfLight = 'c';

    public function dimension(): Dimension
    {
        return Dimension::Speed;
    }

    protected function define(): U
    {
        return match ($this) {
            self::MeterPerSecond => U::of('meter per second')->prefixable()->aliases('m s-1', 'mps', 'm/sec'),
            self::KilometerPerHour => U::of('kilometer per hour', '1000 / 3600')->aliases('kph', 'kmh', 'km/hr', 'kmph', 'km per hour'),
            self::MilePerHour => U::of('mile per hour', '1609.344 / 3600')->aliases('mi/h', 'mi/hr', 'miles an hour'),
            self::Knot => U::of('knot', '1852 / 3600')->aliases('kt', 'kts', 'nmi/h'),
            self::FootPerSecond => U::of('foot per second', '0.3048')->aliases('fps', 'ft/sec'),
            self::InchPerSecond => U::of('inch per second', '0.0254')->aliases('ips'),
            self::SpeedOfLight => U::of('speed of light', 299792458),
        };
    }
}
