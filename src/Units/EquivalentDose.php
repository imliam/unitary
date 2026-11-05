<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: sievert.
 */
enum EquivalentDose: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Sievert = 'Sv';
    case Millisievert = 'mSv';
    case Microsievert = 'µSv';
    case Rem = 'rem';

    public function dimension(): Dimension
    {
        return Dimension::EquivalentDose;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Sievert => U::of('sievert')->prefixable(),
            self::Millisievert => U::of('millisievert', '0.001'),
            self::Microsievert => U::of('microsievert', '10^-6')->aliases('μSv', 'uSv'),
            self::Rem => U::of('rem', '0.01')->prefixable()->plural('rem', 'rems'),
        };
    }
}
