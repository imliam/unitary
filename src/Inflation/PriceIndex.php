<?php

declare(strict_types=1);

namespace Unitary\Inflation;

use Brick\Math\BigRational;
use Unitary\Currency\Currency;

/**
 * A consumer price index: the relative cost of the same basket of goods over time.
 */
interface PriceIndex
{
    public function currency(): Currency;

    /**
     * The index for a year (annual average) or a specific month, or null when
     * the publisher has no figure for that period.
     */
    public function value(int $year, ?int $month = null): ?BigRational;

    public function firstYear(): int;

    /** The latest year with any published figure (possibly only some months). */
    public function latestYear(): int;

    /** The most recent published figure, used for "today's money". */
    public function latest(): BigRational;

    /** The most recent period with a figure, as "YYYY-MM". */
    public function latestPeriod(): string;

    /** Who publishes the series and where it came from. */
    public function source(): string;
}
