<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: second. Months and years use the average Gregorian calendar.
 */
enum Time: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Second = 's';
    case Millisecond = 'ms';
    case Microsecond = 'µs';
    case Nanosecond = 'ns';
    case Minute = 'min';
    case Hour = 'h';
    case Day = 'd';
    case Week = 'wk';
    case Fortnight = 'fortnight';
    case Month = 'mo';
    case Quarter = 'qtr';
    case Year = 'yr';
    case JulianYear = 'a';
    case Decade = 'dec';
    case Century = 'century';
    case Millennium = 'millennium';
    case PlanckTime = 'tP';

    public function dimension(): Dimension
    {
        return Dimension::Time;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Second => U::of('second')->prefixable()->aliases('sec', 'secs'),
            self::Millisecond => U::of('millisecond', '0.001')->aliases('msec'),
            self::Microsecond => U::of('microsecond', '10^-6')->aliases('μs', 'us'),
            self::Nanosecond => U::of('nanosecond', '10^-9'),
            self::Minute => U::of('minute', 60)->aliases('mins', 'm', '′'),
            self::Hour => U::of('hour', 3600)->aliases('hr', 'hrs'),
            self::Day => U::of('day', 86400),
            self::Week => U::of('week', 604800)->aliases('w', 'wks'),
            self::Fortnight => U::of('fortnight', 1209600),
            self::Month => U::of('month', '2629746')->aliases('mos'),
            // Not "quarter" alone, which is a number word: "a quarter mile", "three quarters".
            self::Quarter => U::of('quarter year', '7889238')->aliases('qtrs', 'quarter-year', 'calendar quarter', 'calendar quarters', 'fiscal quarter', 'fiscal quarters'),
            self::Year => U::of('year', 31556952)->aliases('y', 'yrs', 'annum'),
            self::JulianYear => U::of('Julian year', 31557600),
            self::Decade => U::of('decade', 315569520),
            self::Century => U::of('century', 3155695200),
            self::Millennium => U::of('millennium', 31556952000)->plural('millennia', 'millenniums'),
            self::PlanckTime => U::of('Planck time', '5.391247e-44'),
        };
    }
}
