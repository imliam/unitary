<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: mole per cubic meter. pH is logarithmic: [H⁺] = 10^−pH mol/L.
 */
enum MolarConcentration: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case MolePerCubicMeter = 'mol/m³';
    case Molar = 'M';
    case Millimolar = 'mM';
    case Micromolar = 'µM';
    case Nanomolar = 'nM';
    case Ph = 'pH';

    public function dimension(): Dimension
    {
        return Dimension::MolarConcentration;
    }

    protected function define(): U
    {
        return match ($this) {
            self::MolePerCubicMeter => U::of('mole per cubic meter')->aliases('mol/m3'),
            self::Molar => U::of('molar', 1000)->aliases('mol/L', 'mol/l', 'mol/dm³', 'mol/dm3', 'mole per liter')->plural('molar'),
            self::Millimolar => U::of('millimolar', 1)->aliases('mmol/L', 'mmol/l', 'millimole per liter', 'millimoles per liter')->plural('millimolar'),
            self::Micromolar => U::of('micromolar', '0.001')->aliases('µmol/L', 'μmol/L', 'umol/L', 'uM', 'μM')->plural('micromolar'),
            self::Nanomolar => U::of('nanomolar', '10^-6')->aliases('nmol/L', 'nmol/l')->plural('nanomolar'),
            self::Ph => U::of('pH', 1000)->logarithmic('10', -1)->plural('pH'),
        };
    }
}
