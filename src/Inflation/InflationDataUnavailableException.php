<?php

declare(strict_types=1);

namespace Unitary\Inflation;

use RuntimeException;
use Unitary\Currency\Currency;
use Unitary\Exceptions\UnitaryException;

final class InflationDataUnavailableException extends RuntimeException implements UnitaryException
{
    public static function noIndex(Currency $currency): self
    {
        return new self(sprintf(
            'No price index is registered for %s. Pass one to Inflation::adjust() or call Inflation::register().',
            $currency->value,
        ));
    }

    public static function noFigure(PriceIndex $index, string $period): self
    {
        return new self(sprintf(
            'The %s price index has no figure for %s (covers %d to %s)',
            $index->currency()->value,
            $period,
            $index->firstYear(),
            $index->latestPeriod(),
        ));
    }

    public static function notMoney(string $unit): self
    {
        return new self(sprintf('Only currency amounts can be adjusted for inflation, got "%s"', $unit));
    }
}
