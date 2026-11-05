<?php

declare(strict_types=1);

use Unitary\Converter;
use Unitary\Currency\Cache\InMemoryRateCache;
use Unitary\Currency\Currency;
use Unitary\Currency\Providers\ChainedExchangeRates;
use Unitary\Currency\Providers\EuropeanCentralBank;
use Unitary\Currency\Providers\ExchangeRateApi;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Currency\Providers\Frankfurter;
use Unitary\Currency\Providers\OpenExchangeRates;
use Unitary\Exceptions\ExchangeRateUnavailableException;
use Unitary\Tests\Support\FakeHttpClient;

const ECB_XML = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
  <gesmes:subject>Reference rates</gesmes:subject>
  <Cube>
    <Cube time='2026-09-30'>
      <Cube currency='USD' rate='1.1250'/>
      <Cube currency='JPY' rate='165.20'/>
      <Cube currency='GBP' rate='0.8500'/>
    </Cube>
  </Cube>
</gesmes:Envelope>
XML;

it('reads European Central Bank reference rates and derives cross rates', function () {
    $ecb = new EuropeanCentralBank(new FakeHttpClient([EuropeanCentralBank::URL => ECB_XML]));

    expect((string) $ecb->rate(Currency::EUR, Currency::USD)->rate)->toBe('9/8')
        ->and($ecb->rate(Currency::GBP, Currency::USD)->rate->toScale(6, Brick\Math\RoundingMode::HalfUp)->toString())->toBe('1.323529')
        ->and($ecb->rate(Currency::USD, Currency::EUR)->date?->format('Y-m-d'))->toBe('2026-09-30')
        ->and($ecb->rate(Currency::USD, Currency::EUR)->source)->toBe('European Central Bank');
});

it('reads Frankfurter, ExchangeRate-API and Open Exchange Rates responses', function () {
    $http = new FakeHttpClient([
        'https://api.frankfurter.dev/v1/latest' => '{"amount":1.0,"base":"EUR","date":"2026-09-30","rates":{"GBP":0.85,"USD":1.125}}',
        'https://open.er-api.com/v6/latest/USD' => '{"result":"success","time_last_update_unix":1790726400,"base_code":"USD","rates":{"USD":1,"GBP":0.75,"XXX_UNKNOWN":3}}',
        'https://openexchangerates.org/api/latest.json' => '{"timestamp":1790726400,"base":"USD","rates":{"GBP":0.76,"BTC":0.00001}}',
    ]);

    expect((string) (new Frankfurter($http))->rate(Currency::GBP, Currency::USD)->rate)->toBe('45/34')
        ->and((string) (new ExchangeRateApi(http: $http))->rate(Currency::USD, Currency::GBP)->rate)->toBe('3/4')
        ->and((string) (new OpenExchangeRates('app-id', $http))->rate(Currency::USD, Currency::GBP)->rate)->toBe('19/25');
});

it('fetches once per instance and shares tables through a cache', function () {
    $http = new FakeHttpClient([EuropeanCentralBank::URL => ECB_XML]);
    $cache = new InMemoryRateCache();

    $first = new EuropeanCentralBank($http, $cache);
    $first->rate(Currency::EUR, Currency::USD);
    $first->rate(Currency::EUR, Currency::GBP);

    (new EuropeanCentralBank($http, $cache))->rate(Currency::GBP, Currency::JPY);

    expect($http->requests)->toHaveCount(1);
});

it('reports currencies a provider does not cover', function () {
    (new EuropeanCentralBank(new FakeHttpClient([EuropeanCentralBank::URL => ECB_XML])))->rate(Currency::EUR, Currency::NGN);
})->throws(ExchangeRateUnavailableException::class, 'No exchange rate from EUR to NGN is available from European Central Bank');

it('falls back through chained providers', function () {
    $chain = new ChainedExchangeRates(
        new EuropeanCentralBank(new FakeHttpClient([])),
        new FixedExchangeRates(Currency::USD, ['NGN' => 1500]),
    );

    expect((string) $chain->rate(Currency::USD, Currency::NGN)->rate)->toBe('1500');
});

it('wraps transport failures', function () {
    (new ExchangeRateApi(http: new FakeHttpClient([])))->rate(Currency::USD, Currency::GBP);
})->throws(ExchangeRateUnavailableException::class, 'HTTP 404');

it('converts money through a converter instance without global state', function () {
    $converter = new Converter(exchangeRates: new FixedExchangeRates('EUR', ['GBP' => '0.85', 'JPY' => '165']));

    expect($converter->convert('€100 in GBP')->format())->toBe('£85.00')
        ->and($converter->convert(10, 'gbp')->to('JPY')->format())->toBe('¥1,941')
        ->and($converter->convert(10, 'gbp')->to('JPY')->toString())->toBe('1941.1764705882352941 JPY');
});
