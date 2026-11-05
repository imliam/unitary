<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: radian.
 */
enum Angle: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Radian = 'rad';
    case Milliradian = 'mrad';
    case Degree = '°';
    case Gradian = 'grad';
    case Arcminute = 'arcmin';
    case Arcsecond = 'arcsec';
    case Turn = 'tr';
    case Quadrant = 'quadrant';
    case NatoMil = 'NATO mil';

    public function dimension(): Dimension
    {
        return Dimension::Angle;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Radian => U::of('radian')->prefixable()->aliases('ʳ'),
            self::Milliradian => U::of('milliradian', '0.001'),
            self::Degree => U::of('degree', 'pi / 180')->aliases('deg', 'degs', 'º'),
            self::Gradian => U::of('gradian', 'pi / 200')->aliases('gon', 'grade', 'grades', 'ᵍ'),
            self::Arcminute => U::of('arcminute', 'pi / 10800')->aliases('arc minute', 'arc minutes', 'minute of arc', 'minutes of arc', 'MOA'),
            self::Arcsecond => U::of('arcsecond', 'pi / 648000')->aliases('arc second', 'arc seconds', 'second of arc', 'seconds of arc'),
            self::Turn => U::of('turn', '2 * pi')->aliases('revolution', 'revolutions', 'rev', 'cycle', 'cycles', 'rotation', 'rotations'),
            self::Quadrant => U::of('quadrant', 'pi / 2')->aliases('right angle', 'right angles'),
            self::NatoMil => U::of('NATO mil', '2 * pi / 6400')->aliases('angular mil', 'angular mils'),
        };
    }
}
