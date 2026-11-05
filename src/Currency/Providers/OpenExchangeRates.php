<?php

declare(strict_types=1);

namespace Unitary\Currency\Providers;

use DateTimeImmutable;
use DateTimeInterface;
use Unitary\Currency\Cache\RateCache;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRate;
use Unitary\Currency\HistoricalExchangeRateProvider;
use Unitary\Currency\Http\HttpClient;
use Unitary\Currency\RateTable;

/**
 * Open Exchange Rates: ~170 currencies plus precious metals, hourly updates.
 * Requires an App ID (the free plan works).
 *
 * @see https://openexchangerates.org
 */
final class OpenExchangeRates extends RateTableProvider implements HistoricalExchangeRateProvider
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $appId,
        ?HttpClient $http = null,
        ?RateCache $cache = null,
        int $ttl = 3600,
    ) {
        parent::__construct($http, $cache, $ttl);
    }

    public function name(): string
    {
        return 'Open Exchange Rates';
    }

    public function rateAt(Currency $from, Currency $to, DateTimeInterface $date): ExchangeRate
    {
        return $this->tableAt($date)->rate($from, $to);
    }

    protected function fetch(): RateTable
    {
        return $this->tableFrom('latest.json');
    }

    /** End-of-day rates for $date (UTC). */
    protected function fetchAt(DateTimeImmutable $date): RateTable
    {
        return $this->tableFrom('historical/'.$date->format('Y-m-d').'.json');
    }

    private function tableFrom(string $path): RateTable
    {
        $data = $this->getJson('https://openexchangerates.org/api/'.$path.'?show_alternative=0&app_id='.rawurlencode($this->appId));

        return RateTable::fromRaw(
            Currency::tryFrom(is_string($data['base'] ?? null) ? $data['base'] : '') ?? Currency::USD,
            self::arrayAt($data, 'rates', $this->name()),
            self::dateFromTimestamp($data['timestamp'] ?? null),
            $this->name(),
        );
    }
}
