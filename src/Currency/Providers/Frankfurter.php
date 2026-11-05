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
 * Frankfurter: an open-source JSON API over central bank data. Free, no API key.
 * Point $baseUrl at your own instance if you self-host it.
 *
 * @see https://frankfurter.dev
 */
final class Frankfurter extends RateTableProvider implements HistoricalExchangeRateProvider
{
    public function __construct(
        ?HttpClient $http = null,
        ?RateCache $cache = null,
        int $ttl = 3600,
        private readonly string $baseUrl = 'https://api.frankfurter.dev/v1',
    ) {
        parent::__construct($http, $cache, $ttl);
    }

    public function name(): string
    {
        return 'Frankfurter';
    }

    public function rateAt(Currency $from, Currency $to, DateTimeInterface $date): ExchangeRate
    {
        return $this->tableAt($date)->rate($from, $to);
    }

    protected function fetch(): RateTable
    {
        return $this->tableFrom('latest');
    }

    /** Frankfurter returns the closest earlier business day for weekends and holidays. */
    protected function fetchAt(DateTimeImmutable $date): RateTable
    {
        return $this->tableFrom($date->format('Y-m-d'));
    }

    private function tableFrom(string $path): RateTable
    {
        $data = $this->getJson(rtrim($this->baseUrl, '/').'/'.$path.'?base=EUR');
        $date = is_string($data['date'] ?? null) ? new DateTimeImmutable($data['date']) : null;

        return RateTable::fromRaw(Currency::EUR, self::arrayAt($data, 'rates', $this->name()), $date, $this->name());
    }
}
