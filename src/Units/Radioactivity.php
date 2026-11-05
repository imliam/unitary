<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: becquerel.
 */
enum Radioactivity: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Becquerel = 'Bq';
    case Curie = 'Ci';
    case Rutherford = 'Rd';

    public function dimension(): Dimension
    {
        return Dimension::Radioactivity;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Becquerel => U::of('becquerel')->prefixable(),
            self::Curie => U::of('curie', '3.7e10')->prefixable(),
            self::Rutherford => U::of('rutherford', '10^6'),
        };
    }
}
