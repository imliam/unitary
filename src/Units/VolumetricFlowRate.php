<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: cubic meter per second.
 */
enum VolumetricFlowRate: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case CubicMeterPerSecond = 'm³/s';
    case CubicMeterPerHour = 'm³/h';
    case LiterPerSecond = 'L/s';
    case LiterPerMinute = 'L/min';
    case LiterPerHour = 'L/h';
    case GallonPerMinute = 'gal/min';
    case GallonPerHour = 'gal/h';
    case CubicFootPerMinute = 'ft³/min';
    case CubicFootPerSecond = 'ft³/s';

    public function dimension(): Dimension
    {
        return Dimension::VolumetricFlowRate;
    }

    protected function define(): U
    {
        return match ($this) {
            self::CubicMeterPerSecond => U::of('cubic meter per second')->aliases('m3/s', 'cumecs', 'cumec'),
            self::CubicMeterPerHour => U::of('cubic meter per hour', '1 / 3600')->aliases('m3/h', 'm3/hr'),
            self::LiterPerSecond => U::of('liter per second', '0.001')->aliases('l/s', 'lps'),
            self::LiterPerMinute => U::of('liter per minute', '0.001 / 60')->aliases('l/min', 'lpm', 'LPM'),
            self::LiterPerHour => U::of('liter per hour', '0.001 / 3600')->aliases('l/h', 'lph'),
            self::GallonPerMinute => U::of('gallon per minute', '0.003785411784 / 60')->aliases('gpm', 'GPM', 'US gpm'),
            self::GallonPerHour => U::of('gallon per hour', '0.003785411784 / 3600')->aliases('gph', 'GPH'),
            self::CubicFootPerMinute => U::of('cubic foot per minute', '0.028316846592 / 60')->aliases('cfm', 'CFM', 'ft3/min'),
            self::CubicFootPerSecond => U::of('cubic foot per second', '0.028316846592')->aliases('cfs', 'cusec', 'cusecs', 'ft3/s'),
        };
    }
}
