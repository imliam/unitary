<?php

declare(strict_types=1);

namespace Unitary\Units\Novelty;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Historical, astronomical and jokey durations. Base unit: second.
 */
enum NoveltyTime: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Shake = 'shake';
    case Blink = 'blink';
    case Mississippi = 'Mississippi';
    case Nanocentury = 'nanocentury';
    case Moment = 'moment';
    case Microcentury = 'microcentury';
    case SiderealDay = 'sidereal day';
    case Sol = 'sol';
    case SynodicMonth = 'lunar month';
    case TropicalYear = 'tropical year';
    case SiderealYear = 'sidereal year';

    public function dimension(): Dimension
    {
        return Dimension::Time;
    }

    protected function define(): U
    {
        return match ($this) {
            // Nuclear physics slang: 10 ns, "a shake of a lamb's tail".
            self::Shake => U::of('shake', '10^-8'),
            // Nominal 1/3 s, inside the 100–400 ms range given by Harvard BioNumbers (BNID 100706).
            self::Blink => U::of('blink', '1 / 3')->aliases('blink of an eye', 'eye blink'),
            // The counting convention: one Mississippi, two Mississippi...
            self::Mississippi => U::of('Mississippi', 1)->aliases('Mississippis', 'one-Mississippi')->plural('Mississippi'),
            // 10⁻⁹ Gregorian centuries, about π seconds (Duff's rule of thumb).
            self::Nanocentury => U::of('nanocentury', '3155695200 * 10^-9')->plural('nanocenturies'),
            // Medieval unit, 1/40 of an hour (Bede, De temporum ratione).
            self::Moment => U::of('moment', 90),
            // 10⁻⁶ Gregorian centuries, about 52.6 minutes: the "lecture length" joke attributed to Fermi.
            self::Microcentury => U::of('microcentury', '3155695200 * 10^-6')->plural('microcenturies'),
            // Mean sidereal day: 23 h 56 min 4.0905 s.
            self::SiderealDay => U::of('sidereal day', '86164.0905'),
            // NASA: a Martian solar day is 24 h 39 min 35.244 s.
            self::Sol => U::of('sol', '88775.244')->aliases('Martian day', 'Martian days', 'Mars day', 'Mars days'),
            // Mean synodic month: 29.530588853 days.
            self::SynodicMonth => U::of('lunar month', '29.530588853 * 86400')->aliases('synodic month', 'synodic months', 'lunation', 'lunations'),
            // Mean tropical year (J2000): 365.24219 days.
            self::TropicalYear => U::of('tropical year', '365.24219 * 86400')->aliases('solar year', 'solar years'),
            // Sidereal year (J2000): 365.256363004 days.
            self::SiderealYear => U::of('sidereal year', '365.256363004 * 86400'),
        };
    }
}
