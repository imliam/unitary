<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Dimensionless ratios. One is what's left when units cancel out (m/m).
 */
enum Ratio: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case One = '';
    case Percent = '%';
    case Permille = '‰';
    case Permyriad = '‱';
    case Karat = 'karat';
    case PartsPerMillion = 'ppm';
    case PartsPerBillion = 'ppb';
    case Decibel = 'dB';
    case Bel = 'bel';
    case Neper = 'Np';
    case Octave = 'oct';
    case Semitone = 'semitone';
    case Cent = 'cent';

    public function dimension(): Dimension
    {
        return Dimension::Dimensionless;
    }

    protected function define(): U
    {
        return match ($this) {
            self::One => U::of('one')->aliases('1', 'ratio', 'times', 'x', '×')->plural('ones'),
            self::Percent => U::of('percent', '0.01')->aliases('per cent', 'pct', 'percentage'),
            self::Permille => U::of('per mille', '0.001')->aliases('permille', 'per mil')->plural('per mille'),
            self::Permyriad => U::of('permyriad', '0.0001')->aliases('per myriad', 'per ten thousand', 'basis point'),
            // Gold purity in 24ths: 18 karat is 75% gold. Not the carat, a unit of mass.
            self::Karat => U::of('karat', '1 / 24')->aliases('karats'),
            self::PartsPerMillion => U::of('part per million', '10^-6')->plural('parts per million'),
            self::PartsPerBillion => U::of('part per billion', '10^-9')->plural('parts per billion'),
            // Logarithmic ratios, all expressed as power ratios.
            self::Decibel => U::of('decibel')->logarithmic('10', 10)->aliases('db'),
            self::Bel => U::of('bel')->logarithmic('10', 1),
            // Nepers measure field (amplitude) ratios, so as a power ratio 1 Np = e²: 1 Np ≈ 8.686 dB.
            self::Neper => U::of('neper')->logarithmic('e', '1/2'),
            self::Octave => U::of('octave')->logarithmic('2', 1),
            self::Semitone => U::of('semitone')->logarithmic('2', 12)->aliases('half step', 'half steps'),
            self::Cent => U::of('cent')->logarithmic('2', 1200)->aliases('musical cent', 'musical cents'),
        };
    }
}
