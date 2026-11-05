<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: katal.
 */
enum CatalyticActivity: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Katal = 'kat';
    case EnzymeUnit = 'U';

    public function dimension(): Dimension
    {
        return Dimension::CatalyticActivity;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Katal => U::of('katal')->prefixable(),
            self::EnzymeUnit => U::of('enzyme unit', '1 / 60 * 10^-6')->aliases('IU'),
        };
    }
}
