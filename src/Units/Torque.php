<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: newton meter.
 */
enum Torque: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case NewtonMeter = 'N⋅m';
    case KilonewtonMeter = 'kN⋅m';
    case PoundFoot = 'lbf⋅ft';
    case PoundInch = 'lbf⋅in';
    case KilogramForceMeter = 'kgf⋅m';

    public function dimension(): Dimension
    {
        return Dimension::Torque;
    }

    protected function define(): U
    {
        return match ($this) {
            self::NewtonMeter => U::of('newton meter')->aliases('N·m', 'N m', 'Nm', 'N-m', 'newton-meter', 'newton-meters'),
            self::KilonewtonMeter => U::of('kilonewton meter', 1000)->aliases('kN·m', 'kNm', 'kN m'),
            self::PoundFoot => U::of('pound-foot', '0.3048 * 4.4482216152605')->aliases('lbf·ft', 'lbf ft', 'lb ft', 'lb-ft', 'lb·ft', 'pound foot')->plural('pound-feet', 'pound feet'),
            self::PoundInch => U::of('pound-inch', '0.0254 * 4.4482216152605')->aliases('lbf·in', 'lbf in', 'lb in', 'lb-in', 'pound inch', 'pound inches'),
            self::KilogramForceMeter => U::of('kilogram-force meter', '9.80665')->aliases('kgf·m', 'kgf m', 'kgm'),
        };
    }
}
