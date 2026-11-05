<?php

declare(strict_types=1);

namespace Unitary\Cli;

use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRate;
use Unitary\Currency\ExchangeRateProvider;

/**
 * Remembers the last rate used, so --json can report where a conversion's rate came from.
 *
 * @internal
 */
final class RecordingExchangeRates implements ExchangeRateProvider
{
    public ?ExchangeRate $last = null;

    public function __construct(public readonly ExchangeRateProvider $provider)
    {
    }

    public function rate(Currency $from, Currency $to): ExchangeRate
    {
        return $this->last = $this->provider->rate($from, $to);
    }
}
