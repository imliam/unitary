<?php

declare(strict_types=1);

namespace Unitary\Currency\Providers;

use DateTimeInterface;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRate;
use Unitary\Currency\ExchangeRateProvider;
use Unitary\Currency\HistoricalExchangeRateProvider;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Tries each provider in turn: e.g. the ECB for majors, falling back to a
 * broader API for everything else, or to fixed rates when offline. Historical
 * lookups skip providers that only know today's rates.
 */
final readonly class ChainedExchangeRates implements HistoricalExchangeRateProvider
{
    /** @var list<ExchangeRateProvider> */
    private array $providers;

    public function __construct(ExchangeRateProvider ...$providers)
    {
        $this->providers = array_values($providers);
    }

    public function rate(Currency $from, Currency $to): ExchangeRate
    {
        $last = null;

        foreach ($this->providers as $provider) {
            try {
                return $provider->rate($from, $to);
            } catch (ExchangeRateUnavailableException $e) {
                $last = $e;
            }
        }

        throw ExchangeRateUnavailableException::for($from, $to, 'any configured provider', $last);
    }

    public function rateAt(Currency $from, Currency $to, DateTimeInterface $date): ExchangeRate
    {
        $last = null;

        foreach ($this->providers as $provider) {
            if (! $provider instanceof HistoricalExchangeRateProvider) {
                continue;
            }

            try {
                return $provider->rateAt($from, $to, $date);
            } catch (ExchangeRateUnavailableException $e) {
                $last = $e;
            }
        }

        throw ExchangeRateUnavailableException::for($from, $to, 'any configured historical provider on '.$date->format('Y-m-d'), $last);
    }
}
