<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: meter.
 */
enum Length: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Meter = 'm';
    case Kilometer = 'km';
    case Centimeter = 'cm';
    case Millimeter = 'mm';
    case Micrometer = 'µm';
    case Nanometer = 'nm';
    case Picometer = 'pm';
    case Angstrom = 'Å';
    case Inch = 'in';
    case Foot = 'ft';
    case Yard = 'yd';
    case Mile = 'mi';
    case NauticalMile = 'nmi';
    case Thou = 'mil';
    case Hand = 'hh';
    case Link = 'li';
    case Rod = 'rd';
    case Chain = 'ch';
    case Furlong = 'fur';
    case League = 'lea';
    case Fathom = 'ftm';
    case UsSurveyFoot = 'ftUS';
    case ScandinavianMile = 'scand mil';
    case Sun = 'sun (JP)';
    case Shaku = 'shaku';
    case Ken = 'ken (JP)';
    case Jo = 'jō (JP)';
    case Ri = 'ri (JP)';
    case Point = 'pt';
    case Pica = 'pica';
    case EarthRadius = 'R⊕';
    case SolarRadius = 'R☉';
    case AstronomicalUnit = 'au';
    case LightYear = 'ly';
    case Parsec = 'pc';
    case PlanckLength = 'lP';

    public function dimension(): Dimension
    {
        return Dimension::Length;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Meter => U::of('meter')->prefixable(),
            self::Kilometer => U::of('kilometer', 1000)->aliases('klick', 'kms'),
            self::Centimeter => U::of('centimeter', '0.01'),
            self::Millimeter => U::of('millimeter', '0.001'),
            self::Micrometer => U::of('micrometer', '10^-6')->aliases('μm', 'um', 'micron'),
            self::Nanometer => U::of('nanometer', '10^-9'),
            self::Picometer => U::of('picometer', '10^-12'),
            self::Angstrom => U::of('angstrom', '10^-10')->aliases('ångström', 'Å'),
            self::Inch => U::of('inch', '0.0254')->aliases('"', '″', '”', "''"),
            self::Foot => U::of('foot', '0.3048')->aliases("'", '′', '’'),
            self::Yard => U::of('yard', '0.9144'),
            self::Mile => U::of('mile', '1609.344')->aliases('statute mile'),
            self::NauticalMile => U::of('nautical mile', 1852)->aliases('NM'),
            self::Thou => U::of('thou', '0.0000254')->aliases('mils', 'thousandth of an inch'),
            self::Hand => U::of('hand', '0.1016'),
            self::Link => U::of('link', '0.201168'),
            self::Rod => U::of('rod', '5.0292')->aliases('pole', 'perch'),
            self::Chain => U::of('chain', '20.1168'),
            self::Furlong => U::of('furlong', '201.168'),
            self::League => U::of('league', '4828.032'),
            self::Fathom => U::of('fathom', '1.8288'),
            self::UsSurveyFoot => U::of('US survey foot', '1200 / 3937')->plural('US survey feet'),
            self::ScandinavianMile => U::of('Scandinavian mile', 10000)->aliases('Swedish mile', 'Norwegian mile', 'Scandinavian mil'),
            // Japanese lengths are defined from the shaku, 10/33 metres.
            self::Sun => U::of('Japanese sun', '1 / 33')->plural('Japanese sun'),
            self::Shaku => U::of('shaku', '10 / 33')->plural('shaku'),
            self::Ken => U::of('Japanese ken', '20 / 11')->plural('Japanese ken'),
            self::Jo => U::of('Japanese jō', '100 / 33')->aliases('Japanese jo')->plural('Japanese jō'),
            self::Ri => U::of('Japanese ri', '43200 / 11')->plural('Japanese ri'),
            self::Point => U::of('point', '0.0254 / 72')->aliases('typographic point'),
            self::Pica => U::of('pica', '0.0254 / 6'),
            // IAU nominal values: Earth's equatorial radius and the Sun's radius.
            self::EarthRadius => U::of('Earth radius', '6.3781e6')->aliases('R_earth', 'Rearth')->plural('Earth radii'),
            self::SolarRadius => U::of('solar radius', '6.957e8')->aliases('R_sun', 'Rsun')->plural('solar radii'),
            self::AstronomicalUnit => U::of('astronomical unit', 149597870700)->aliases('AU', 'ua'),
            self::LightYear => U::of('light-year', 9460730472580800)->aliases('light year', 'light years', 'lightyear', 'lightyears'),
            self::Parsec => U::of('parsec', '149597870700 * 648000 / pi')->prefixable(),
            self::PlanckLength => U::of('Planck length', '1.616255e-35'),
        };
    }
}
