<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Unitary\Converter;
use Unitary\Currency\Providers\EuropeanCentralBank;
use Unitary\Currency\Providers\ExchangeRateApi;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Currency\Providers\Frankfurter;
use Unitary\Dimension;
use Unitary\Exceptions\ParseException;
use Unitary\Laravel\ExchangeRatesFactory;
use Unitary\Laravel\Facades\Unitary as UnitaryFacade;
use Unitary\Quantity;
use Unitary\Tests\Laravel\TestCase;
use Unitary\Unitary;

use function Unitary\convert;

uses(TestCase::class);

it('binds a configured converter and points the static helpers at it', function () {
    $converter = app(Converter::class);

    expect($converter)->toBe(app('unitary'))
        ->and(Unitary::converter())->toBe($converter)
        ->and($converter->exchangeRates())->toBeInstanceOf(FixedExchangeRates::class)
        ->and(convert('£8 in USD')->format())->toBe('$10.00')
        ->and(UnitaryFacade::convert('1 mi to km')->toString())->toBe('1.609344 km');
});

it('builds providers from config', function (array $config, ?string $class) {
    $provider = app(ExchangeRatesFactory::class)->make($config);

    expect($provider === null ? null : $provider::class)->toBe($class);
})->with([
    [['provider' => 'ecb'], EuropeanCentralBank::class],
    [['provider' => 'frankfurter', 'cache_store' => false], Frankfurter::class],
    [['provider' => 'exchangerate-api', 'exchangerate-api' => ['key' => 'abc']], ExchangeRateApi::class],
    [['provider' => null], null],
    [['provider' => FixedRatesForTest::class], FixedRatesForTest::class],
]);

it('requires an app id for Open Exchange Rates', function () {
    app(ExchangeRatesFactory::class)->make(['provider' => 'openexchangerates']);
})->throws(InvalidArgumentException::class, 'app_id is required');

it('renders the @quantity Blade directive', function () {
    expect(Blade::render('@quantity($q, 2)', ['q' => Quantity::of(1234.5678, 'm')]))->toBe('1,234.57 m')
        ->and(Blade::render('@quantity("5 ft 11 in", 1, "cm")'))->toBe('180.3 cm')
        ->and(Blade::render('@quantity($q)', ['q' => Quantity::of(23, 'GBP')]))->toBe('£23.00')
        ->and(Blade::render('[@quantity(null)]'))->toBe('[]');
});

it('writes units in the app language in the @quantity Blade directive', function () {
    app()->setLocale('fr');

    expect(Blade::render("@quantity('2 in', display: 'long')"))->toBe("2\u{00A0}pouces")
        ->and(Blade::render("@quantity('180 cm', 0, usage: 'person-height', display: 'long')"))->toBe("180\u{00A0}centimètres");

    config(['unitary.region' => 'US']);

    expect(Blade::render("@quantity('180 cm', 0, usage: 'person-height', display: 'long')"))->toBe("5 pieds 11\u{00A0}pouces");
});

it('picks units for the region in the @quantity Blade directive', function () {
    app()->setLocale('en_GB');

    expect(Blade::render("@quantity('80 km', 0, usage: 'road')"))->toBe('50 mi')
        ->and(Blade::render("@quantity('80 kg', usage: 'person')"))->toBe('12 st 8 lb')
        ->and(Blade::render("@quantity('80 km', 0, 'km', usage: 'road')"))->toBe('80 km');

    app()->setLocale('en');

    expect(Blade::render("@quantity('80 km', 0, usage: 'road')"))->toBe('80 km');

    config(['unitary.region' => 'US']);

    expect(Blade::render("@quantity('180 cm', usage: 'person-height')"))->toBe('5 ft 11 in')
        ->and(Blade::render("@quantity('20 °C', usage: 'weather')"))->toBe('68 °F');
});

it('reads quantities from requests', function () {
    $request = Request::create('/', 'POST', ['weight' => '2 lb 3 oz', 'count' => '5', 'empty' => '']);

    expect($request->quantity('weight')?->toString())->toBe('35 oz')
        ->and($request->quantity('count', unit: 'kg')?->toString())->toBe('5 kg')
        ->and($request->quantity('empty', '1 kg')?->toString())->toBe('1 kg')
        ->and($request->quantity('missing'))->toBeNull()
        ->and($request->quantity('weight', dimension: Dimension::Mass)?->dimension())->toBe(Dimension::Mass);

    $request->quantity('count');
})->throws(ParseException::class, 'a unit is required');

it('sums quantities in collections', function () {
    expect(collect(['1 kg', Quantity::of(500, 'g'), '1 lb'])->sumQuantities()?->toString())->toBe('1.95359237 kg')
        ->and(collect([['w' => '1 ft'], ['w' => '6 in']])->sumQuantities('w', 'in')?->toString())->toBe('18 in')
        ->and(collect([1, 2])->sumQuantities(fn (int $n) => "{$n} m")?->toString())->toBe('3 m')
        ->and(collect()->sumQuantities())->toBeNull()
        ->and(collect()->sumQuantities(unit: 'kg')?->toString())->toBe('0 kg');
});

final class FixedRatesForTest implements \Unitary\Currency\ExchangeRateProvider
{
    public function rate(\Unitary\Currency\Currency $from, \Unitary\Currency\Currency $to): \Unitary\Currency\ExchangeRate
    {
        return new \Unitary\Currency\ExchangeRate($from, $to, \Brick\Math\BigRational::one());
    }
}
