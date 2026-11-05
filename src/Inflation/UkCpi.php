<?php

declare(strict_types=1);

namespace Unitary\Inflation;

/**
 * ONS Consumer Prices Index (D7BT, 2015 = 100), the UK's headline measure.
 * Only available from 1988; use UkRpi for older dates.
 */
final class UkCpi extends BundledPriceIndex
{
    protected function file(): string
    {
        return 'uk-cpi.php';
    }
}
