<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: mole.
 */
enum AmountOfSubstance: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Mole = 'mol';
    case Millimole = 'mmol';
    case Entity = 'entities';

    public function dimension(): Dimension
    {
        return Dimension::AmountOfSubstance;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Mole => U::of('mole')->prefixable(),
            self::Millimole => U::of('millimole', '0.001'),
            self::Entity => U::of('entity', '1 / 6.02214076e23')->aliases('particle', 'particles', 'molecule', 'molecules', 'atom', 'atoms'),
        };
    }
}
