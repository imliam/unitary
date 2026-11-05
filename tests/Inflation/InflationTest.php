<?php

declare(strict_types=1);

use Brick\Math\BigRational;
use Unitary\Currency\Currency;
use Unitary\Inflation\Inflation;
use Unitary\Inflation\InflationDataUnavailableException;
use Unitary\Inflation\PriceIndex;
use Unitary\Inflation\UkCpi;
use Unitary\Inflation\UkRpi;
use Unitary\Inflation\UsCpi;
use Unitary\Quantity;

afterEach(fn () => Inflation::reset());

// Figures checked against the publishers' own releases when the data was bundled.
it('bundles the official BLS CPI-U series', function () {
    $cpi = new UsCpi();

    expect($cpi->currency())->toBe(Currency::USD)
        ->and($cpi->series())->toBe('CUUR0000SA0')
        ->and((string) $cpi->value(1913)?->toBigDecimal())->toBe('9.9')
        ->and((string) $cpi->value(1970)?->toBigDecimal())->toBe('38.8')
        ->and((string) $cpi->value(1913, 1)?->toBigDecimal())->toBe('9.8')
        ->and($cpi->firstYear())->toBe(1913)
        ->and($cpi->source())->toContain('Bureau of Labor Statistics');
});

it('bundles the official ONS series', function () {
    expect((string) (new UkRpi())->value(1970)?->toBigDecimal())->toBe('73.1')
        ->and((string) (new UkRpi())->value(2000)?->toBigDecimal())->toBe('671.8')
        ->and((new UkRpi())->firstYear())->toBe(1800)
        ->and((string) (new UkCpi())->value(2000)?->toBigDecimal())->toBe('72.7')
        ->and((new UkCpi())->firstYear())->toBe(1988)
        ->and((new UkCpi())->source())->toContain('D7BT');
});

it('adjusts money between years', function () {
    $adjusted = Inflation::adjust(Quantity::of(100, Currency::USD), 1970, 2000, new FixedIndex([1970 => '38.8', 2000 => '172.2']));

    expect((string) $adjusted->toDecimal(2))->toBe('443.81')
        ->and($adjusted->unit())->toBe(Currency::USD);
});

it('defaults to the latest published month for today\'s money', function () {
    $index = new FixedIndex([1970 => '50'], latest: '200');

    expect((string) Inflation::adjust(Quantity::of(10, Currency::USD), 1970, index: $index)->toDecimal())->toBe('40');
});

it('uses monthly figures for dates and the latest month for a partial year', function () {
    $index = new FixedIndex([2020 => '100'], monthly: ['2020-06' => '120'], latest: '150', latestPeriod: '2026-08');

    expect((string) Inflation::adjust(Quantity::of(10, Currency::USD), new DateTimeImmutable('2020-06-15'), 2026, $index)->toDecimal())->toBe('12.5')
        ->and((string) Inflation::factor($index, 2020))->toBe('3/2');
});

it('picks the index from the currency', function () {
    Inflation::register(new FixedIndex([1950 => '1', 2020 => '10']));

    expect((string) Inflation::adjust(Quantity::of(5, 'USD'), 1950, 2020)->toDecimal())->toBe('50')
        ->and(Inflation::indexFor(Currency::GBP))->toBeInstanceOf(UkRpi::class);
});

it('fails clearly without data', function (Closure $call, string $message) {
    expect($call)->toThrow(InflationDataUnavailableException::class, $message);
})->with([
    'no index' => [fn () => Inflation::adjust(Quantity::of(5, 'EUR'), 1990), 'No price index is registered for EUR'],
    'out of range' => [fn () => Inflation::adjust(Quantity::of(5, 'USD'), 1850), 'has no figure for 1850'],
    'not money' => [fn () => Inflation::adjust(Quantity::of(5, 'kg'), 1990), 'Only currency amounts'],
    'wrong currency index' => [fn () => Inflation::adjust(Quantity::of(5, 'GBP'), 1990, index: new UsCpi()), 'No price index is registered for GBP'],
]);

it('parses inflation phrases', function (string $input, string $expected) {
    Inflation::register(new FixedIndex([1950 => '25', 1970 => '40', 2020 => '250'], latest: '300'));
    Inflation::register(new FixedIndex([1970 => '75'], latest: '1500', currency: Currency::GBP));

    expect(Inflation::tryParse($input, fn (string $amount) => Quantity::parse($amount))?->toString())->toBe($expected);
})->with([
    ["£100 in 1970 in today's money", '2000 GBP'],
    ['£100 in 1970 in today’s pounds', '2000 GBP'],
    ['£100 in 1970 money', '2000 GBP'],
    ['$100 in 1950 in 2020 dollars', '1000 USD'],
    ['$100 (1950) in 2020', '1000 USD'],
    ['$100 from 1950 to 2020', '1000 USD'],
    ['$100 in 1950 dollars in 2020 dollars', '1000 USD'],
    ['$100 in 1970 now', '750 USD'],
    ['$100 in 1950', '1200 USD'],
]);

it('ignores phrases that are not about money over time', function (string $input) {
    expect(Inflation::tryParse($input, fn (string $amount) => Quantity::parse($amount)))->toBeNull();
})->with(['5 km in 1970', '£23 in USD', '100 USD', 'nonsense in 1970', '£100 in 1970 in USD']);

final class FixedIndex implements PriceIndex
{
    /**
     * @param  array<int, string>  $annual
     * @param  array<string, string>  $monthly
     */
    public function __construct(
        private array $annual,
        private array $monthly = [],
        private ?string $latest = null,
        private string $latestPeriod = '2026-08',
        private Currency $currency = Currency::USD,
    ) {
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function value(int $year, ?int $month = null): ?BigRational
    {
        $value = $month === null ? ($this->annual[$year] ?? null) : ($this->monthly[sprintf('%04d-%02d', $year, $month)] ?? null);

        return $value === null ? null : BigRational::of($value);
    }

    public function firstYear(): int
    {
        return (int) min(array_keys($this->annual));
    }

    public function latestYear(): int
    {
        return (int) substr($this->latestPeriod, 0, 4);
    }

    public function latest(): BigRational
    {
        return BigRational::of($this->latest ?? (string) end($this->annual));
    }

    public function latestPeriod(): string
    {
        return $this->latestPeriod;
    }

    public function source(): string
    {
        return 'test';
    }
}
