<?php

declare(strict_types=1);

namespace Unitary\Currency;

use Unitary\Exceptions\ExchangeRateUnavailableException;

interface ExchangeRateProvider
{
    /**
     * @throws ExchangeRateUnavailableException
     */
    public function rate(Currency $from, Currency $to): ExchangeRate;
}
