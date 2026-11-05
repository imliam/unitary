<?php

declare(strict_types=1);

namespace Unitary\Currency\Providers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRate;
use Unitary\Currency\HistoricalExchangeRateProvider;
use Unitary\Currency\RateTable;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Daily euro reference rates from the European Central Bank: free, no API key,
 * ~30 major currencies, updated around 16:00 CET on working days. History
 * goes back to 4 January 1999.
 *
 * @see https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/html/index.en.html
 */
final class EuropeanCentralBank extends RateTableProvider implements HistoricalExchangeRateProvider
{
    public const URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    public const HISTORY_90_DAYS_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';

    public const HISTORY_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';

    /** @var array<string, string> downloaded history files, keyed by URL */
    private array $history = [];

    public function name(): string
    {
        return 'European Central Bank';
    }

    public function rateAt(Currency $from, Currency $to, DateTimeInterface $date): ExchangeRate
    {
        return $this->tableAt($date)->rate($from, $to);
    }

    protected function fetch(): RateTable
    {
        $xml = $this->http->get(self::URL);

        $date = preg_match("/time=['\"](\\d{4}-\\d{2}-\\d{2})['\"]/", $xml, $time) ? $time[1] : null;

        return $this->parseDay($xml, $date);
    }

    /** Picks the latest working day on or before $date. */
    protected function fetchAt(DateTimeImmutable $date): RateTable
    {
        $day = $date->format('Y-m-d');

        // The 90-day file is ~50 KB; the full history is several MB.
        $recent = $day >= gmdate('Y-m-d', time() - 85 * 86400);
        $url = $recent ? self::HISTORY_90_DAYS_URL : self::HISTORY_URL;
        $xml = $this->history[$url] ??= $this->http->get($url);

        preg_match_all("/<Cube\\s+time=['\"](\\d{4}-\\d{2}-\\d{2})['\"]\\s*>(.*?)<\\/Cube>/s", $xml, $days, PREG_SET_ORDER);

        // The files list days newest first.
        foreach ($days as [, $time, $rates]) {
            if ($time <= $day) {
                return $this->parseDay($rates, $time);
            }
        }

        throw new ExchangeRateUnavailableException(sprintf('%s has no reference rates on or before %s (they start on 1999-01-04)', $this->name(), $day));
    }

    private function parseDay(string $xml, ?string $date): RateTable
    {
        if (! preg_match_all("/currency=['\"]([A-Z]{3})['\"]\\s+rate=['\"]([\\d.]+)['\"]/", $xml, $matches, PREG_SET_ORDER)) {
            throw ExchangeRateUnavailableException::fetchFailed($this->name(), 'no rates found in response');
        }

        return RateTable::fromRaw(
            Currency::EUR,
            array_column($matches, 2, 1),
            $date === null ? null : new DateTimeImmutable($date, new DateTimeZone('Europe/Berlin')),
            $this->name(),
        );
    }
}
