<?php

declare(strict_types=1);

namespace Unitary\Currency;

use DateTimeInterface;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * A provider that can also answer "what was the rate on this date?", e.g. to
 * convert an invoice at the rate on the day it was issued.
 */
interface HistoricalExchangeRateProvider extends ExchangeRateProvider
{
    /**
     * The rate in effect on $date. Sources that only publish on business days
     * return the most recent rate on or before it; check ExchangeRate::$date.
     *
     * @throws ExchangeRateUnavailableException
     */
    public function rateAt(Currency $from, Currency $to, DateTimeInterface $date): ExchangeRate;
}
