<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: newton.
 */
enum Force: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Newton = 'N';
    case Kilonewton = 'kN';
    case Dyne = 'dyn';
    case PoundForce = 'lbf';
    case KilogramForce = 'kgf';
    case Poundal = 'pdl';
    case Kip = 'kip';

    public function dimension(): Dimension
    {
        return Dimension::Force;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Newton => U::of('newton')->prefixable(),
            self::Kilonewton => U::of('kilonewton', 1000),
            self::Dyne => U::of('dyne', '10^-5'),
            self::PoundForce => U::of('pound-force', '4.4482216152605')->aliases('pound force', 'pounds force', 'lb-f', 'lbF')->plural('pounds-force'),
            self::KilogramForce => U::of('kilogram-force', '9.80665')->aliases('kilogram force', 'kilopond', 'kp')->plural('kilograms-force'),
            self::Poundal => U::of('poundal', '0.138254954376'),
            self::Kip => U::of('kip', '4448.2216152605')->aliases('kipf', 'klbf'),
        };
    }
}
