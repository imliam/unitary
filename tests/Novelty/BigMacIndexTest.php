<?php

declare(strict_types=1);

use Brick\Math\RoundingMode;
use Unitary\Converter;
use Unitary\Currency\Cache\InMemoryRateCache;
use Unitary\Currency\Currency;
use Unitary\Currency\Providers\BigMacIndex;
use Unitary\Exceptions\ExchangeRateUnavailableException;
use Unitary\Tests\Support\FakeHttpClient;

const BIG_MAC_CSV = <<<'CSV'
date,iso_a3,currency_code,name,local_price,dollar_ex,dollar_price,USD,EUR,GBP,JPY,CNY
2025-07-01,GBR,GBP,Britain,5.29,0.73,7.24,0,0,0,0,0
2025-07-01,USA,USD,United States,5.99,1,5.99,0,0,0,0,0
2025-07-01,JPN,JPY,Japan,480,147,3.26,0,0,0,0,0
2026-07-01,GBR,GBP,Britain,5.49,0.74187,7.40021836709936,0.18975,0.04534,0,1.39967,0.89077
2026-07-01,EUZ,EUR,Euro area,6.19,0.87439,7.07922094259998,0.13814,0,-0.04338,1.29558,0.80875
2026-07-01,JPN,JPY,Japan,500,162.135,3.08384987818793,-0.5042,-0.56438,-0.58328,0,-0.21207
2026-07-01,USA,USD,United States,6.22,1,6.22,0,-0.12137,-0.15948,1.01696,0.58922
2026-07-01,XXX,ZZZ,Nowhere,1,1,1,0,0,0,0,0
CSV;

function bigMac(?DateTimeImmutable $date = null, ?FakeHttpClient $http = null): BigMacIndex
{
    return new BigMacIndex($http ?? new FakeHttpClient([BigMacIndex::URL => BIG_MAC_CSV]), date: $date);
}

it('derives PPP rates from local Big Mac prices in the latest survey', function () {
    $rate = bigMac()->rate(Currency::GBP, Currency::JPY);

    expect((string) $rate->rate)->toBe('50000/549')
        ->and($rate->date?->format('Y-m-d'))->toBe('2026-07-01')
        ->and($rate->source)->toBe('The Economist Big Mac index')
        ->and((string) bigMac()->rate(Currency::USD, Currency::GBP)->rate)->toBe('549/622');
});

it('uses the survey in effect on a given date', function () {
    $index = bigMac(new DateTimeImmutable('2026-01-15'));

    expect((string) $index->rate(Currency::GBP, Currency::USD)->rate)->toBe('599/529')
        ->and($index->rate(Currency::GBP, Currency::USD)->date?->format('Y-m-d'))->toBe('2025-07-01');
});

it('converts money so a Big Mac costs the same everywhere', function () {
    $converter = new Converter(exchangeRates: bigMac());

    expect($converter->convert('£5.49 in JPY')->toDecimal(0, RoundingMode::HalfUp)->toString())->toBe('500')
        ->and($converter->convert('€6.19 in USD')->format())->toBe('$6.22');
});

it('caches the parsed table', function () {
    $http = new FakeHttpClient([BigMacIndex::URL => BIG_MAC_CSV]);
    $cache = new InMemoryRateCache();

    (new BigMacIndex($http, $cache))->rate(Currency::GBP, Currency::USD);
    (new BigMacIndex($http, $cache))->rate(Currency::EUR, Currency::JPY);

    expect($http->requests)->toHaveCount(1);
});

it('reports currencies outside the survey and dates before it', function () {
    expect(fn () => bigMac()->rate(Currency::GBP, Currency::NGN))
        ->toThrow(ExchangeRateUnavailableException::class, 'No exchange rate from GBP to NGN')
        ->and(fn () => bigMac(new DateTimeImmutable('1999-01-01'))->rate(Currency::GBP, Currency::USD))
        ->toThrow(ExchangeRateUnavailableException::class, 'no survey on or before 1999-01-01');
});

it('rejects unexpected CSV', function () {
    bigMac(http: new FakeHttpClient([BigMacIndex::URL => "foo,bar\n1,2"]))->rate(Currency::GBP, Currency::USD);
})->throws(ExchangeRateUnavailableException::class, 'unexpected CSV format');
