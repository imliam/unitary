<?php

declare(strict_types=1);

namespace Unitary\Currency\Providers;

use Brick\Math\BigNumber;
use DateTimeImmutable;
use DateTimeInterface;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRate;
use Unitary\Currency\HistoricalExchangeRateProvider;
use Unitary\Currency\RateTable;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Rates you supply yourself: for tests, offline use, or rates from your own database.
 *
 *     new FixedExchangeRates(Currency::USD, ['GBP' => '0.79', 'EUR' => '0.92'])
 *
 *     FixedExchangeRates::dated(Currency::USD, [
 *         '2025-01-01' => ['GBP' => '0.80'],
 *         '2026-01-01' => ['GBP' => '0.75'],
 *     ])
 *
 * Undated rates apply to every date. Dated rates apply from their date until
 * the next one; rate() uses the latest.
 */
final readonly class FixedExchangeRates implements HistoricalExchangeRateProvider
{
    private RateTable $table;

    /** @var list<RateTable> dated tables, newest first */
    private array $history;

    /**
     * @param  array<string, BigNumber|int|float|string>  $rates  ISO code => units per one $base
     * @param  array<string, array<string, BigNumber|int|float|string>>  $history  Y-m-d => rates; prefer dated()
     */
    public function __construct(Currency|string $base, array $rates, ?DateTimeImmutable $date = null, array $history = [])
    {
        $base = self::currency($base);
        $this->table = self::table($base, $rates, $date);

        krsort($history);
        $tables = [];

        foreach ($history as $day => $dayRates) {
            $tables[] = self::table($base, $dayRates, new DateTimeImmutable((string) $day));
        }

        $this->history = $tables;
    }

    /**
     * @param  array<string, array<string, BigNumber|int|float|string>>  $history  Y-m-d => (ISO code => units per one $base)
     */
    public static function dated(Currency|string $base, array $history): self
    {
        if ($history === []) {
            throw new ExchangeRateUnavailableException('FixedExchangeRates::dated() needs at least one dated table');
        }

        krsort($history);
        $latest = (string) array_key_first($history);

        return new self($base, $history[$latest], new DateTimeImmutable($latest), $history);
    }

    public function rate(Currency $from, Currency $to): ExchangeRate
    {
        return $this->table->rate($from, $to);
    }

    public function rateAt(Currency $from, Currency $to, DateTimeInterface $date): ExchangeRate
    {
        if ($this->history === []) {
            return $this->table->rate($from, $to);
        }

        $day = $date->format('Y-m-d');

        foreach ($this->history as $table) {
            if ($table->date !== null && $table->date->format('Y-m-d') <= $day) {
                return $table->rate($from, $to);
            }
        }

        throw new ExchangeRateUnavailableException(sprintf('No fixed exchange rates are defined on or before %s', $day));
    }

    private static function currency(Currency|string $currency): Currency
    {
        return $currency instanceof Currency ? $currency : Currency::from(strtoupper($currency));
    }

    /**
     * @param  array<string, BigNumber|int|float|string>  $rates
     */
    private static function table(Currency $base, array $rates, ?DateTimeImmutable $date): RateTable
    {
        return RateTable::fromRaw(
            $base,
            array_map(fn (BigNumber|int|float|string $rate) => $rate instanceof BigNumber ? (string) $rate : $rate, $rates),
            $date,
            'fixed rates',
        );
    }
}
