<?php

declare(strict_types=1);

namespace Unitary\Currency;

use DateTimeInterface;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Looks up a current or historical rate from any provider, failing clearly
 * when a date is requested from a provider that only knows today's rates.
 */
final class ExchangeRateLookup
{
    /**
     * @throws ExchangeRateUnavailableException
     */
    public static function find(ExchangeRateProvider $provider, Currency $from, Currency $to, ?DateTimeInterface $at = null): ExchangeRate
    {
        if ($at === null) {
            return $provider->rate($from, $to);
        }

        if (! $provider instanceof HistoricalExchangeRateProvider) {
            throw new ExchangeRateUnavailableException(sprintf(
                '%s does not provide historical exchange rates. Use a HistoricalExchangeRateProvider such as Frankfurter or EuropeanCentralBank.',
                $provider::class,
            ));
        }

        return $provider->rateAt($from, $to, $at);
    }
}
