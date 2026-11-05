<?php

declare(strict_types=1);

namespace Unitary\Inflation;

use Brick\Math\BigRational;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;
use Unitary\Currency\Currency;
use Unitary\Quantity;

/**
 * Adjusts money for inflation using a consumer price index:
 *
 *     Inflation::adjust(Quantity::of(100, 'GBP'), 1970);          // in today's money
 *     Inflation::adjust(Quantity::of(100, 'USD'), 1950, 2020);    // in 2020 dollars
 *
 * Years use the annual average; dates use that month's figure. "Today" is
 * the most recent published month. USD uses the BLS CPI-U and GBP the ONS
 * long-run RPI; register other indexes with Inflation::register().
 */
final class Inflation
{
    /** @var array<string, PriceIndex>|null */
    private static ?array $indexes = null;

    private const PERIOD = '\d{4}(?:-\d{2})?';

    /**
     * @throws InflationDataUnavailableException
     *
     * @param  \Unitary\Quantity<\Unitary\Unit>  $money
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public static function adjust(
        Quantity $money,
        int|DateTimeInterface $from,
        int|DateTimeInterface|null $to = null,
        ?PriceIndex $index = null,
    ): Quantity {
        $currency = $money->unit();

        if (! $currency instanceof Currency) {
            throw InflationDataUnavailableException::notMoney($currency->symbol());
        }

        $index ??= self::indexFor($currency) ?? throw InflationDataUnavailableException::noIndex($currency);

        if ($index->currency() !== $currency) {
            throw InflationDataUnavailableException::noIndex($currency);
        }

        return $money->multipliedBy(self::figure($index, $to)->dividedBy(self::figure($index, $from))->simplified());
    }

    /**
     * The multiplier between two periods, e.g. 1970 → today.
     */
    public static function factor(PriceIndex $index, int|DateTimeInterface $from, int|DateTimeInterface|null $to = null): BigRational
    {
        return self::figure($index, $to)->dividedBy(self::figure($index, $from))->simplified();
    }

    public static function indexFor(Currency $currency): ?PriceIndex
    {
        return self::indexes()[$currency->value] ?? null;
    }

    /** Uses this index for its currency, replacing any existing one. */
    public static function register(PriceIndex $index): void
    {
        self::$indexes = [...self::indexes(), $index->currency()->value => $index];
    }

    /** Restores the built-in indexes. */
    public static function reset(): void
    {
        self::$indexes = null;
    }

    /**
     * Parses inflation phrases. $parseQuantity turns the amount text into a
     * Quantity (it may throw or return null). Returns null when the input
     * isn't an inflation phrase about money:
     *
     *     £100 in 1970 in today's money
     *     $100 in 1950 in 2020 dollars
     *     $100 (1950) in 2020
     *     $100 from 1950 to 2020
     *     £100 in 1970 money
     *
     * @param  Closure(string): (Quantity<\Unitary\Unit>|null)  $parseQuantity
     *
     * @throws InflationDataUnavailableException when the phrase matches but no data covers it
     *
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    public static function tryParse(string $input, Closure $parseQuantity): ?Quantity
    {
        $period = self::PERIOD;
        $word = '(?!(?:in|to|into|as)\b)\p{L}+';
        $today = "(?:today[’']?s?|now|current|present(?:\\s+day)?)(?:\\s+{$word})?";

        $pattern = "/^(?<amount>.+?)\\s*(?:\\(\\s*(?<from1>{$period})\\s*\\)|\\s(?:in|from)\\s+(?<from2>{$period})(?:\\s+{$word})?)"
            ."(?:\\s+(?:in|to|into|as)\\s+(?:(?<to>{$period})(?:\\s+{$word})?|(?<today>{$today})))?\\s*$/iu";

        if (! preg_match($pattern, trim($input), $matches)) {
            return null;
        }

        try {
            $money = $parseQuantity(trim($matches['amount']));
        } catch (Throwable) {
            return null;
        }

        if (! $money instanceof Quantity || ! $money->unit() instanceof Currency) {
            return null;
        }

        $from = self::period(($matches['from1'] ?? '') !== '' ? $matches['from1'] : ($matches['from2'] ?? ''));
        $to = ($matches['to'] ?? '') !== '' ? self::period($matches['to']) : null;

        return self::adjust($money, $from, $to);
    }

    private static function period(string $text): int|DateTimeImmutable
    {
        return strlen($text) === 4 ? (int) $text : new DateTimeImmutable($text.'-01');
    }

    /**
     * @throws InflationDataUnavailableException
     */
    private static function figure(PriceIndex $index, int|DateTimeInterface|null $period): BigRational
    {
        if ($period === null) {
            return $index->latest();
        }

        if ($period instanceof DateTimeInterface) {
            $year = (int) $period->format('Y');

            return $index->value($year, (int) $period->format('n'))
                ?? $index->value($year)
                ?? throw InflationDataUnavailableException::noFigure($index, $period->format('Y-m'));
        }

        // The current year has no annual average yet, so use its latest month.
        return $index->value($period)
            ?? ($period === $index->latestYear() ? $index->latest() : null)
            ?? throw InflationDataUnavailableException::noFigure($index, (string) $period);
    }

    /**
     * @return array<string, PriceIndex>
     */
    private static function indexes(): array
    {
        return self::$indexes ??= [
            Currency::USD->value => new UsCpi(),
            Currency::GBP->value => new UkRpi(),
        ];
    }
}
