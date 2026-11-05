<?php

declare(strict_types=1);

use Unitary\Currency\Currency;
use Unitary\Quantity;
use Unitary\Units\Length;
use Unitary\Units\Mass;

use function Unitary\convert;

it('does arithmetic across units of the same dimension', function () {
    $total = Quantity::of(1, Length::Meter)->plus(Quantity::of(50, 'cm'))->minus(Quantity::of(10, 'mm'));

    expect($total->toString())->toBe('1.49 m')
        ->and($total->multipliedBy(2)->toString())->toBe('2.98 m')
        ->and($total->dividedBy(3)->toString())->toBe('0.49666666666666666667 m')
        ->and($total->negated()->abs()->isEqualTo($total))->toBeTrue();
});

it('compares quantities in different units', function () {
    expect(Quantity::of(1, 'mi')->isGreaterThan(Quantity::of(1, 'km')))->toBeTrue()
        ->and(Quantity::of(12, 'in')->isEqualTo(Quantity::of(1, 'ft')))->toBeTrue()
        ->and(Quantity::of(1, 'lb')->isLessThan(Quantity::of(1, 'kg')))->toBeTrue()
        ->and(Quantity::of(0, 'g')->isZero())->toBeTrue();
});

it('formats for humans', function () {
    expect(convert(1234567.891, 'm')->format(2))->toBe('1,234,567.89 m')
        ->and(convert(1234567.891, 'm')->format(1, ',', '.'))->toBe('1.234.567,9 m')
        ->and(convert(-1234.5, Currency::GBP)->format())->toBe('-£1,234.50')
        ->and(convert(1234, Currency::JPY)->format())->toBe('¥1,234')
        ->and(convert(12.5, Currency::PLN)->format())->toBe('12.50 zł')
        ->and(convert(12.5, Currency::CHF)->format())->toBe('12.50 Fr.')
        ->and(convert(12.5, Currency::BMD)->format())->toBe('12.50 BMD')
        ->and(convert(90, '°')->toString())->toBe('90°')
        ->and(convert(5, 'kg')->format(withUnit: false))->toBe('5');
});

it('renders scientific notation', function () {
    expect(convert(1000, 'mi')->to('nm')->toScientific())->toBe('1.609344e+15 nm')
        ->and(convert(1, 'nm')->to('mi')->toScientific(4))->toBe('6.214e-13 mi')
        ->and(convert(9.99999, 'm')->toScientific(3))->toBe('1e+1 m')
        ->and(convert(0, 'm')->toScientific())->toBe('0 m');
});

it('serialises to JSON', function () {
    expect(json_encode(convert('5 ft 11 in')))->toBe('{"value":"71","unit":"in","unitName":"inch","dimension":"length"}');
});

it('accepts floats, numeric strings and big numbers without float noise', function () {
    expect(convert(0.1, 'm')->plus(0.2)->toString())->toBe('0.3 m')
        ->and(convert('123456789012345678901234567890', Mass::Gram)->to('kg')->toString())->toBe('123456789012345678901234567.89 kg')
        ->and(convert(Brick\Math\BigDecimal::of('1.5'), 'km')->to('m')->toString())->toBe('1500 m');
});

it('formats with locale-specific separators without losing precision', function () {
    expect(convert(1234567.891, 'm')->format(locale: 'de'))->toBe('1.234.567,891 m')
        ->and(convert(1234567.891, 'm')->format(locale: 'en_US'))->toBe('1,234,567.891 m')
        ->and(convert('1000', 'mi')->to('nm')->format(locale: 'de'))->toBe('1.609.344.000.000.000 nm')
        ->and(convert(1234.5, Currency::EUR)->format(locale: 'de'))->toBe('€1.234,50')
        ->and(convert(1234567.891, 'm')->format(1, '.', ' ', locale: 'de'))->toBe('1 234 567.9 m')
        ->and((string) convert(1234567.891, 'm'))->toBe('1234567.891 m');
})->skip(! class_exists(NumberFormatter::class), 'requires ext-intl');

it('exposes full unit names', function () {
    expect(convert(23, 'gbp')->unitName())->toBe('Pound sterling')
        ->and(convert('100 km/h')->unitName())->toBe('kilometer per hour')
        ->and(convert('5 GBP/kWh')->unitName())->toBe('Pound sterling per kilowatt-hour')
        ->and(convert('5 Gm')->unitName())->toBe('gigameter')
        ->and(convert('£23')->jsonSerialize()['unitName'])->toBe('Pound sterling');
});
