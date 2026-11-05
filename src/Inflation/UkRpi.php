<?php

declare(strict_types=1);

namespace Unitary\Inflation;

/**
 * ONS long-run Retail Prices Index (CDKO, Jan 1974 = 100). Annual figures
 * from 1800 and monthly from 1947, so it covers the widest span of UK history.
 * This is the default index for GBP.
 */
final class UkRpi extends BundledPriceIndex
{
    protected function file(): string
    {
        return 'uk-rpi-long-run.php';
    }
}
