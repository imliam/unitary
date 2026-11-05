<?php

declare(strict_types=1);

namespace Unitary\Inflation;

/**
 * US Bureau of Labor Statistics CPI-U (CUUR0000SA0): all items, US city
 * average, not seasonally adjusted. Annual averages from 1913.
 */
final class UsCpi extends BundledPriceIndex
{
    protected function file(): string
    {
        return 'us-cpi-u.php';
    }
}
