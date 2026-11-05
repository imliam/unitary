<?php

declare(strict_types=1);

namespace Unitary\Currency\Providers;

use Brick\Math\BigRational;
use DateTimeImmutable;
use Unitary\Currency\Cache\RateCache;
use Unitary\Currency\Currency;
use Unitary\Currency\Http\HttpClient;
use Unitary\Currency\RateTable;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Purchasing-power-parity "exchange rates" implied by The Economist's Big Mac
 * index. One unit of A is worth (price of a Big Mac in B ÷ price in A) of B,
 * so £5.49 always buys "one Big Mac's worth" in any covered currency.
 *
 * Covers ~55 currencies, published twice a year. Pass a date to use the
 * survey in effect on that day; by default the latest one is used.
 *
 * @see https://github.com/TheEconomist/big-mac-data
 */
final class BigMacIndex extends RateTableProvider
{
    public const URL = 'https://raw.githubusercontent.com/TheEconomist/big-mac-data/master/output-data/big-mac-raw-index.csv';

    public function __construct(
        ?HttpClient $http = null,
        ?RateCache $cache = null,
        int $ttl = 86400,
        private readonly ?DateTimeImmutable $date = null,
        private readonly string $url = self::URL,
    ) {
        parent::__construct($http, $cache, $ttl);
    }

    public function name(): string
    {
        return 'The Economist Big Mac index';
    }

    protected function cacheKey(): string
    {
        return parent::cacheKey().($this->date === null ? '' : '_'.$this->date->format('Ymd'));
    }

    protected function fetch(): RateTable
    {
        $surveys = $this->surveys($this->http->get($this->url));
        $date = $this->surveyDate(array_keys($surveys));
        $prices = $surveys[$date];

        $usPrice = $prices[Currency::USD->value] ?? throw ExchangeRateUnavailableException::fetchFailed($this->name(), "no US price for {$date}");
        $rates = [];

        foreach ($prices as $code => $price) {
            $rates[$code] = $price->dividedBy($usPrice)->simplified();
        }

        return new RateTable(Currency::USD, $rates, new DateTimeImmutable($date), $this->name());
    }

    /**
     * Local Big Mac prices grouped by survey date.
     *
     * @return array<string, array<string, BigRational>>
     */
    private function surveys(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        $header = str_getcsv(array_shift($lines) ?? '', ',', '"', '');
        $dateColumn = array_search('date', $header, true);
        $codeColumn = array_search('currency_code', $header, true);
        $priceColumn = array_search('local_price', $header, true);

        if ($dateColumn === false || $codeColumn === false || $priceColumn === false) {
            throw ExchangeRateUnavailableException::fetchFailed($this->name(), 'unexpected CSV format');
        }

        $surveys = [];

        foreach ($lines as $line) {
            $row = str_getcsv($line, ',', '"', '');
            $date = (string) ($row[$dateColumn] ?? '');
            $currency = Currency::tryFrom((string) ($row[$codeColumn] ?? ''));
            $price = (string) ($row[$priceColumn] ?? '');

            if ($currency === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! is_numeric($price) || (float) $price <= 0) {
                continue;
            }

            // Keep the first row per currency (e.g. "Euro area" rather than individual euro countries).
            $surveys[$date][$currency->value] ??= BigRational::of($price);
        }

        if ($surveys === []) {
            throw ExchangeRateUnavailableException::fetchFailed($this->name(), 'no prices found in CSV');
        }

        return $surveys;
    }

    /**
     * The latest survey on or before the requested date.
     *
     * @param  list<string>  $dates
     */
    private function surveyDate(array $dates): string
    {
        sort($dates);

        if ($this->date === null) {
            return $dates[count($dates) - 1];
        }

        $wanted = $this->date->format('Y-m-d');
        $chosen = null;

        foreach ($dates as $date) {
            if ($date <= $wanted) {
                $chosen = $date;
            }
        }

        return $chosen ?? throw ExchangeRateUnavailableException::fetchFailed($this->name(), "no survey on or before {$wanted}");
    }
}
