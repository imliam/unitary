<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: coulomb.
 */
enum ElectricCharge: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Coulomb = 'C';
    case AmpereHour = 'Ah';
    case MilliampereHour = 'mAh';
    case ElementaryCharge = 'e';
    case Faraday = 'Fd';

    public function dimension(): Dimension
    {
        return Dimension::ElectricCharge;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Coulomb => U::of('coulomb')->prefixable(),
            self::AmpereHour => U::of('ampere-hour', 3600)->prefixable()->aliases('amp hour', 'amp hours', 'ampere hour', 'ampere hours', 'A⋅h', 'A h'),
            self::MilliampereHour => U::of('milliampere-hour', '3.6')->aliases('milliamp hour', 'milliamp hours', 'mA⋅h', 'mA h'),
            self::ElementaryCharge => U::of('elementary charge', '1.602176634e-19'),
            self::Faraday => U::of('faraday', '96485.33212'),
        };
    }
}
