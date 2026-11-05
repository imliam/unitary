<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigNumber;
use DateTimeInterface;
use DateTimeZone;
use Unitary\Color\Color;
use Unitary\Geo\Coordinate;
use Unitary\Scales\Scale;
use Unitary\Time\ZonedTime;

if (! function_exists('Unitary\convert')) {
    /**
     * convert(23, 'gbp'), convert('£23 in USD'), convert("13' 11\" in m"), convert(1000, Length::Mile)->to('nm')
     *
     * @see Converter::convert()
     *
     * @return ($value is DateTimeInterface ? ZonedTime : ($unit is Unit ? Quantity<Unit> : ($unit is DateTimeZone ? ZonedTime : Quantity<Unit>|Color|ZonedTime|Coordinate|Scale)))
     */
    function convert(
        BigNumber|int|float|string|DateTimeInterface $value,
        Unit|DateTimeZone|string|null $unit = null,
    ): Quantity|Color|ZonedTime|Coordinate|Scale {
        return Unitary::convert($value, $unit);
    }
}
