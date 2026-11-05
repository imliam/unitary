<?php

declare(strict_types=1);

use Unitary\Currency\Cache\InMemoryRateCache;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRateLookup;
use Unitary\Currency\Providers\ChainedExchangeRates;
use Unitary\Currency\Providers\EuropeanCentralBank;
use Unitary\Currency\Providers\ExchangeRateApi;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Currency\Providers\Frankfurter;
use Unitary\Currency\Providers\OpenExchangeRates;
use Unitary\Exceptions\ExchangeRateUnavailableException;
use Unitary\Tests\Support\FakeHttpClient;

const ECB_HISTORY_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
  <Cube>
    <Cube time="2020-03-02"><Cube currency="USD" rate="1.1104"/><Cube currency="GBP" rate="0.86518"/></Cube>
    <Cube time="2020-02-28"><Cube currency="USD" rate="1.0977"/><Cube currency="GBP" rate="0.85315"/></Cube>
    <Cube time="2020-02-27"><Cube currency="USD" rate="1.0948"/><Cube currency="GBP" rate="0.84868"/></Cube>
  </Cube>
</gesmes:Envelope>
XML;

it('picks the latest ECB business day on or before the requested date', function () {
    $ecb = new EuropeanCentralBank(new FakeHttpClient([EuropeanCentralBank::HISTORY_URL => ECB_HISTORY_XML]));

    $sunday = $ecb->rateAt(Currency::EUR, Currency::USD, new DateTimeImmutable('2020-03-01'));
    $monday = $ecb->rateAt(Currency::EUR, Currency::USD, new DateTimeImmutable('2020-03-02'));

    expect((string) $sunday->rate->toBigDecimal())->toBe('1.0977')
        ->and($sunday->date?->format('Y-m-d'))->toBe('2020-02-28')
        ->and((string) $monday->rate->toBigDecimal())->toBe('1.1104')
        ->and($ecb->rateAt(Currency::GBP, Currency::USD, new DateTimeImmutable('2020-02-27'))->rate->toScale(4, Brick\Math\RoundingMode::HalfUp)->toString())->toBe('1.2900');
});

it('reports dates before ECB history begins', function () {
    (new EuropeanCentralBank(new FakeHttpClient([EuropeanCentralBank::HISTORY_URL => ECB_HISTORY_XML])))
        ->rateAt(Currency::EUR, Currency::USD, new DateTimeImmutable('1998-06-01'));
})->throws(ExchangeRateUnavailableException::class, 'no reference rates on or before 1998-06-01');

it('uses the ECB 90-day file for recent dates', function () {
    $recent = new DateTimeImmutable('-5 days');
    $xml = str_replace('2020-02-27', $recent->format('Y-m-d'), ECB_HISTORY_XML);
    $http = new FakeHttpClient([EuropeanCentralBank::HISTORY_90_DAYS_URL => $xml]);

    (new EuropeanCentralBank($http))->rateAt(Currency::EUR, Currency::USD, $recent);

    expect($http->requests)->toBe([EuropeanCentralBank::HISTORY_90_DAYS_URL]);
});

it('requests dated Frankfurter and Open Exchange Rates endpoints', function () {
    $http = new FakeHttpClient([
        'https://api.frankfurter.dev/v1/2020-03-01' => '{"amount":1.0,"base":"EUR","date":"2020-02-28","rates":{"GBP":0.85315,"USD":1.0977}}',
        'https://openexchangerates.org/api/historical/2020-03-01.json' => '{"timestamp":1583107200,"base":"USD","rates":{"GBP":0.7834}}',
    ]);

    $frankfurter = (new Frankfurter($http))->rateAt(Currency::EUR, Currency::GBP, new DateTimeImmutable('2020-03-01'));
    $oxr = (new OpenExchangeRates('app-id', $http))->rateAt(Currency::USD, Currency::GBP, new DateTimeImmutable('2020-03-01'));

    expect((string) $frankfurter->rate)->toBe('17063/20000')
        ->and($frankfurter->date?->format('Y-m-d'))->toBe('2020-02-28')
        ->and((string) $oxr->rate)->toBe('3917/5000');
});

it('caches historical tables per date', function () {
    $http = new FakeHttpClient(['https://api.frankfurter.dev/v1/2020-03-01' => '{"base":"EUR","date":"2020-02-28","rates":{"USD":1.0977}}']);
    $cache = new InMemoryRateCache();

    (new Frankfurter($http, $cache))->rateAt(Currency::EUR, Currency::USD, new DateTimeImmutable('2020-03-01'));
    (new Frankfurter($http, $cache))->rateAt(Currency::USD, Currency::EUR, new DateTimeImmutable('2020-03-01 18:00'));

    expect($http->requests)->toHaveCount(1);
});

it('refuses future dates', function () {
    (new Frankfurter(new FakeHttpClient([])))->rateAt(Currency::EUR, Currency::USD, new DateTimeImmutable('+1 year'));
})->throws(ExchangeRateUnavailableException::class, 'future date');

it('supports dated fixed rates', function () {
    $rates = FixedExchangeRates::dated('USD', [
        '2025-01-01' => ['GBP' => '0.80'],
        '2026-01-01' => ['GBP' => '0.75'],
    ]);

    expect((string) $rates->rate(Currency::USD, Currency::GBP)->rate)->toBe('3/4')
        ->and((string) $rates->rateAt(Currency::USD, Currency::GBP, new DateTimeImmutable('2025-06-30'))->rate)->toBe('4/5')
        ->and((string) $rates->rateAt(Currency::USD, Currency::GBP, new DateTimeImmutable('2026-03-01'))->rate)->toBe('3/4')
        ->and((string) (new FixedExchangeRates('USD', ['GBP' => '0.5']))->rateAt(Currency::USD, Currency::GBP, new DateTimeImmutable('1990-01-01'))->rate)->toBe('1/2')
        ->and(fn () => $rates->rateAt(Currency::USD, Currency::GBP, new DateTimeImmutable('2024-12-31')))->toThrow(ExchangeRateUnavailableException::class);
});

it('skips non-historical providers in a chain', function () {
    $chain = new ChainedExchangeRates(
        new ExchangeRateApi(http: new FakeHttpClient([])),
        FixedExchangeRates::dated('EUR', ['2020-01-01' => ['USD' => '1.1']]),
    );

    expect((string) $chain->rateAt(Currency::EUR, Currency::USD, new DateTimeImmutable('2020-06-01'))->rate)->toBe('11/10');
});

it('explains when a provider has no history', function () {
    ExchangeRateLookup::find(new ExchangeRateApi(http: new FakeHttpClient([])), Currency::USD, Currency::GBP, new DateTimeImmutable('2020-01-01'));
})->throws(ExchangeRateUnavailableException::class, 'does not provide historical exchange rates');

it('looks up current rates when no date is given', function () {
    $rate = ExchangeRateLookup::find(new FixedExchangeRates('USD', ['GBP' => '0.8']), Currency::USD, Currency::GBP);

    expect((string) $rate->rate)->toBe('4/5');
});
