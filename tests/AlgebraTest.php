<?php

declare(strict_types=1);

use Unitary\CompoundUnit;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Dimension;
use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Quantity;
use Unitary\Unitary;
use Unitary\Units\Area;
use Unitary\Units\Force;
use Unitary\Units\Power;
use Unitary\Units\Ratio;
use Unitary\Units\Speed;

use function Unitary\convert;

beforeEach(fn () => Unitary::useExchangeRates(new FixedExchangeRates('USD', ['GBP' => '0.8', 'EUR' => '0.9'])));
afterEach(fn () => Unitary::setConverter(null));

it('multiplies and divides quantities into derived units', function () {
    expect(Quantity::of(90, 'km')->dividedBy(Quantity::of(3, 'h'))->toString())->toBe('30 km/h')
        ->and(Quantity::of(90, 'km')->dividedBy(Quantity::of(3, 'h'))->unit())->toBe(Speed::KilometerPerHour)
        ->and(Quantity::of(3, 'm')->multipliedBy(Quantity::of(4, 'm'))->unit())->toBe(Area::SquareMeter)
        ->and(Quantity::of(3, 'm')->multipliedBy(Quantity::of(4, 'ft'))->toString())->toBe('3.6576 m²')
        ->and(Quantity::of(100, 'J')->dividedBy(Quantity::of(2, 's'))->unit())->toBe(Power::Watt)
        ->and(Quantity::of(3, 'm')->power(3)->toString())->toBe('27 m³')
        ->and(Quantity::of(6, 'm')->dividedBy(Quantity::of(2, 'm'))->unit())->toBe(Ratio::One);
});

it('parses compound unit text', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ['5 kg⋅m/s² to N', '5 N'],
    ['5 kg m/s^2 to N', '5 N'],
    ['1000 W/m² in kW/m²', '1 kW/m²'],
    ['60 mi/h to km/h', '96.56064 km/h'],
    ['1 J/s in W', '1 W'],
    ['3 kW h in MJ', '10.8 MJ'],
]);

it('keeps compounds without a name', function () {
    $unit = convert('5 GBP/kWh')->unit();

    expect($unit)->toBeInstanceOf(CompoundUnit::class)
        ->and($unit->dimension())->toBe(Dimension::Derived)
        ->and($unit->symbol())->toBe('GBP/kWh')
        ->and($unit->name())->toBe('Pound sterling per kilowatt-hour');
});

it('converts rates, including currency rates', function () {
    expect(convert('$120/hr')->multipliedBy(Quantity::of(42, 'h'))->format())->toBe('$5,040.00')
        ->and(convert('$120/hr')->multipliedBy(Quantity::of(30, 'min'))->toString())->toBe('60 USD')
        ->and(convert('€30/day in €/month')->format(2))->toBe('913.11 EUR/mo')
        ->and(convert('$20/day + $300/week in /month')->toString())->toBe('1913.175 USD/mo')
        ->and(convert('30/week as /month')->toString())->toBe('130.44375 1/mo')
        ->and(convert('£10/h in USD/h')->toString())->toBe('12.5 USD/h');
});

it('simplifies to coherent SI units', function () {
    expect(Quantity::of(36, 'km/h')->simplify()->toString())->toBe('10 m/s')
        ->and(Quantity::of(1, 'kWh')->simplify()->toString())->toBe('3600000 J')
        ->and(Quantity::of(3, 'm')->multipliedBy(Quantity::of(1, 'ft'))->simplify()->unit())->toBe(Area::SquareMeter)
        ->and(convert('5 kg m/s^2')->simplify()->unit())->toBe(Force::Newton);
});

it('keeps distinct dimensions that share a vector apart', function () {
    convert('5 J to N⋅m');
})->throws(IncompatibleUnitsException::class);

it('rejects mixing incompatible compounds', function () {
    convert('5 m/s to kg/s');
})->throws(IncompatibleUnitsException::class);

it('reads "p" between unit symbols as "per"', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ['10 kmps', '10 km/s'],
    ['10 kgph to g/s', '2.7777777777777777778 g/s'],
    ['5 cmps in m/s', '0.05 m/s'],
    ['3 mLpmin to L/h', '0.18 L/h'],
    ['5 ppm', '5 ppm'],
    ['5 hp', '5 hp'],
    ['5 rpm', '5 rpm'],
]);
