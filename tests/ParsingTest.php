<?php

declare(strict_types=1);

use Unitary\Color\Color;
use Unitary\Currency\Currency;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Exceptions\ExchangeRateUnavailableException;
use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Exceptions\ParseException;
use Unitary\Quantity;
use Unitary\Time\ZonedTime;
use Unitary\Unitary;
use Unitary\Units\Length;

use function Unitary\convert;

beforeEach(fn () => Unitary::useExchangeRates(new FixedExchangeRates('USD', ['GBP' => '0.8', 'EUR' => '0.9', 'JPY' => '150'])));
afterEach(fn () => Unitary::setConverter(null));

it('parses currency amounts in every reasonable shape', function (mixed $value, mixed $unit) {
    $quantity = convert($value, $unit);

    expect($quantity)->toBeInstanceOf(Quantity::class)
        ->and($quantity->unit())->toBe(Currency::GBP)
        ->and((string) $quantity->toDecimal())->toBe('23');
})->with([
    [23, 'gbp'],
    ['23', 'GBP'],
    [23, Currency::GBP],
    ['£23', null],
    ['23 GBP', null],
    ['GBP 23', null],
    ['23gbp', null],
    ['23 pounds sterling', null],
    ['23 quid', null],
    ['£ 23', null],
    ['23£', null],
]);

it('parses freeform quantities', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ['5 km', '5 km'],
    ['5km', '5 km'],
    ['1,234.5 m', '1234.5 m'],
    ['-40 °C', '-40 °C'],
    ['−3 °F', '-3 °F'],
    ['1.5e9 nm', '1500000000 nm'],
    ['2½ cups', '2.5 cup'],
    ['1 1/2 tsp', '1.5 tsp'],
    ['¾ in', '0.75 in'],
    ['10 m2', '10 m²'],
    ['10 sq ft', '10 ft²'],
    ['9.81 m/s^2', '9.81 m/s²'],
    ['6.5 L/100km', '6.5 L/100km'],
    ['100 kilometres per hour', '100 km/h'],
    ['3 gigawatt hours', '3 GWh'],
    ['20 degrees Celsius', '20 °C'],
    ['20° C', '20 °C'],
    ['5 Fl. Oz.', '5 fl oz'],
    ['8 GiB', '8 GiB'],
    ['300 Mbps', '300 Mbit/s'],
]);

it('sums compound quantities into the smallest unit', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ["13' 11\"", '167 in'],
    ['13′ 11″', '167 in'],
    ['5 ft 11 in', '71 in'],
    ['5ft11in', '71 in'],
    ['5 feet and 11 inches', '71 in'],
    ['1 mile 200 yards', '1960 yd'],
    ['2 lb, 3 oz', '35 oz'],
    ['1h 30m', '90 min'],
    ['2h30m15s', '9015 s'],
    ['1 day 6 hours', '30 h'],
    ['-5 ft 6 in', '-66 in'],
]);

it('converts freeform input with a target', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ["13' 11\" in m", '4.2418 m'],
    ['5 ft 11 in to cm', '180.34 cm'],
    ['5 in in cm', '12.7 cm'],
    ['1 mile to km', '1.609344 km'],
    ['100 °C → °F', '212 °F'],
    ['1 GiB -> MB', '1073.741824 MB'],
    ['1 h = s', '3600 s'],
    ['2 hours in minutes', '120 min'],
    ['1 tonne as pounds', '2204.6226218487758072 lb'],
]);

it('converts currencies from freeform text', function () {
    expect(convert('£23 in USD')->format())->toBe('$28.75')
        ->and(convert('$1,000 to £')->format())->toBe('£800.00')
        ->and(convert('€90 in GBP')->toString())->toBe('80 GBP')
        ->and(convert('100 USD to yen')->format())->toBe('¥15,000')
        ->and(convert('23 pounds in dollars')->format())->toBe('$28.75')
        ->and(convert('23 pounds in kg')->toString())->toBe('10.43262451 kg')
        ->and(convert('£23 in dollars')->format())->toBe('$28.75');
});

it('falls back to colors and times', function () {
    expect(convert('#ff0000'))->toBeInstanceOf(Color::class)
        ->and((string) convert('#ff0000 to hsl'))->toBe('hsl(0 100% 50%)')
        ->and((string) convert('rebeccapurple in rgb'))->toBe('rgb(102 51 153)')
        ->and(convert('2026-10-01 10:00 Europe/London in America/New_York')->toString())->toBe('2026-10-01T05:00:00-04:00[America/New_York]')
        ->and(convert('10:00', 'Europe/London'))->toBeInstanceOf(ZonedTime::class)
        ->and(convert(new DateTimeImmutable('2026-01-01 12:00 UTC'), 'Asia/Tokyo')->format('H:i'))->toBe('21:00')
        ->and(convert(0, new DateTimeZone('UTC'))->format('Y-m-d'))->toBe('1970-01-01');
});

it('explains why a conversion failed', function (string $input, string $exception, string $message) {
    expect(fn () => convert($input))->toThrow($exception, $message);
})->with([
    ['5 kg to GBP', IncompatibleUnitsException::class, 'Cannot convert kg (mass) to GBP (currency)'],
    ['5 florbs', ParseException::class, 'Unable to parse "5 florbs"'],
    ['5 kg 3 m', ParseException::class, 'Unable to parse "5 kg 3 m"'],
    ['', ParseException::class, 'Unable to parse'],
]);

it('requires an exchange rate provider for currency conversion', function () {
    Unitary::useExchangeRates(null);

    convert('£5 in USD');
})->throws(ExchangeRateUnavailableException::class, 'No exchange rate provider is configured');

it('requires a unit for bare numbers', function () {
    convert(5);
})->throws(ParseException::class, 'a unit is required');

it('parses quantities through the typed helpers', function () {
    expect(Quantity::parse('5 ft 11 in')->to(Length::Centimeter)->toString())->toBe('180.34 cm')
        ->and(Unitary::quantity('5 ft 11 in')->unit())->toBe(Length::Inch)
        ->and(Unitary::quantity(5, 'ft')->unit())->toBe(Length::Foot);
});

it('reads the source in the target dimension when the default reading does not fit', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ['100c in f', '212 °F'],
    ['37c to f', '98.6 °F'],
    ['98.6f in c', '37 °C'],
    ['100 c to kelvin', '373.15 K'],
    ['0.5 c in km/s', '149896.229 km/s'],
]);

it('falls back to the calculator for arithmetic and percentages', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected)
        ->and(Unitary::tryConvert($input)?->toString())->toBe($expected);
})->with([
    ['50 + 4', '54'],
    ['65% of 30', '19.5'],
    ['(2 + 3) × 4', '20'],
    ['2^10', '1024'],
    ['20% of 3 km in m', '600 m'],
]);

it('does not let the calculator fallback ignore unknown words', function (string $input) {
    expect(Unitary::tryConvert($input))->toBeNull();
})->with(['asdf 5 qwer', '3 pizzas at $12 each', '1/0']);
