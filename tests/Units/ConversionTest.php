<?php

declare(strict_types=1);

use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Prefix;
use Unitary\Units\DigitalStorage;
use Unitary\Units\Length;
use Unitary\Units\Mass;
use Unitary\Units\Temperature;

use function Unitary\convert;

it('converts known reference values exactly', function (string $value, string $from, string $to, string $expected) {
    expect((string) convert($value, $from)->to($to)->toDecimal())->toBe($expected);
})->with([
    ['1', 'mi', 'm', '1609.344'],
    ['1', 'in', 'cm', '2.54'],
    ['1', 'ft', 'in', '12'],
    ['1', 'nmi', 'km', '1.852'],
    ['1', 'lb', 'g', '453.59237'],
    ['1', 'st', 'lb', '14'],
    ['1', 'oz', 'g', '28.349523125'],
    ['1', 'ozt', 'g', '31.1034768'],
    ['1', 't', 'kg', '1000'],
    ['1', 'ton', 'lb', '2000'],
    ['1', 'LT', 'lb', '2240'],
    ['1', 'gal', 'L', '3.785411784'],
    ['1', 'imp gal', 'L', '4.54609'],
    ['1', 'gal', 'fl oz', '128'],
    ['1', 'cup', 'tbsp', '16'],
    ['1', 'tbsp', 'tsp', '3'],
    ['1', 'AU tbsp', 'mL', '20'],
    ['1', 'metric tbsp', 'metric tsp', '3'],
    ['3', 'metric cup', 'metric tbsp', '50'],
    ['1', 'dsp', 'mL', '10'],
    ['1', 'gi', 'fl oz', '4'],
    ['1', 'imp gi', 'imp fl oz', '5'],
    ['1', 'imp pt', 'imp gi', '4'],
    ['8', 'imp tbsp', 'imp fl oz', '5'],
    ['1', 'imp tbsp', 'imp tsp', '3'],
    ['1', 'scand mil', 'km', '10'],
    ['1', 'fl oz', 'fl dr', '8'],
    ['576', 'gtt', 'fl oz', '1'],
    ['128', 'pinch', 'fl oz', '1'],
    ['1', 'imp cup', 'mL', '284.130625'],
    ['1', 'imp dsp', 'imp tsp', '2'],
    ['1', 'metric pt', 'mL', '500'],
    ['1', 'metric fl oz', 'mL', '30'],
    ['1', 'JP cup', 'mL', '200'],
    ['10', 'gō', 'shō', '1'],
    ['100', 'shō', 'koku', '1'],
    ['1', 'tō', 'shō', '10'],
    ['33', 'shaku', 'm', '10'],
    ['10', 'sun (JP)', 'shaku', '1'],
    ['1', 'ken (JP)', 'shaku', '6'],
    ['1', 'jō (JP)', 'shaku', '10'],
    ['1', 'ri (JP)', 'shaku', '12960'],
    ['121', 'tsubo', 'm²', '400'],
    ['1', 'se (JP)', 'tsubo', '30'],
    ['1', 'chō', 'tsubo', '3000'],
    ['1', 'dunam', 'm²', '1000'],
    ['1', 'monme', 'fun (JP)', '10'],
    ['1', 'monme', 'g', '3.75'],
    ['1', '‱', '%', '0.01'],
    ['1', 'cal_IT', 'J', '4.1868'],
    ['9', 'BTU_th', 'J', '9489.1523804'],
    ['1', 'therm US', 'J', '105480400'],
    ['18', 'karat', '%', '75'],
    ['4', 'qtr', 'yr', '1'],
    ['1', 'M☉', 'kg', '1988470000000000000000000000000'],
    ['1', 'M⊕', 'kg', '5972200000000000000000000'],
    ['1', 'R☉', 'km', '695700'],
    ['1', 'R⊕', 'km', '6378.1'],
    ['1', 'L☉', 'W', '382800000000000000000000000'],
    ['1', 'acre', 'ft²', '43560'],
    ['1', 'ha', 'm²', '10000'],
    ['1', 'mi²', 'acre', '640'],
    ['1', 'day', 's', '86400'],
    ['1', 'yr', 'd', '365.2425'],
    ['1', 'kWh', 'J', '3600000'],
    ['1', 'kcal', 'cal', '1000'],
    ['1', 'atm', 'Pa', '101325'],
    ['1', 'atm', 'Torr', '760'],
    ['1', 'bar', 'kPa', '100'],
    ['1', 'kn', 'km/h', '1.852'],
    ['1', 'GiB', 'MiB', '1024'],
    ['1', 'GB', 'MB', '1000'],
    ['1', 'B', 'bit', '8'],
    ['1', 'turn', '°', '360'],
    ['1', 'grad', '°', '0.9'],
    ['60', 'rpm', 'Hz', '1'],
    ['1', 'g₀', 'm/s²', '9.80665'],
    ['1', 'kgf', 'N', '9.80665'],
    ['100', '°C', '°F', '212'],
    ['0', '°C', 'K', '273.15'],
    ['-40', '°F', '°C', '-40'],
    ['0', 'K', '°F', '-459.67'],
    ['491.67', '°R', '°C', '0'],
    ['80', '°Ré', '°C', '100'],
    ['0', '°De', '°C', '100'],
    ['33', '°N', '°C', '100'],
    ['60', '°Rø', '°C', '100'],
    ['5', 'L/100km', 'km/L', '20'],
    ['20', 'km/L', 'L/100km', '5'],
    ['1', 'Ah', 'C', '3600'],
    ['1', 'Ci', 'Bq', '37000000000'],
    ['1', 'g/cm³', 'kg/m³', '1000'],
    ['1', 'ly', 'km', '9460730472580.8'],
    ['1', 'au', 'm', '149597870700'],
]);

it('handles very large and very small numbers without losing precision', function () {
    expect((string) convert(1000, Length::Mile)->to(Length::Nanometer)->toDecimal())->toBe('1609344000000000')
        ->and((string) convert('1e30', 'ly')->to('pm')->toDecimal())->toBe('9460730472580800000000000000000000000000000000000000000000')
        ->and((string) convert(1, 'pm')->to('ly')->toScientific(12))->toBe('1.05700083402e-28 ly')
        ->and((string) convert(1, 'QB')->to('B')->toDecimal())->toBe('1000000000000000000000000000000');
});

it('keeps exact rationals through chains of conversions', function () {
    $quantity = convert(1, 'ft');

    foreach (['in', 'cm', 'mi', 'nmi', 'ly', 'µm', 'ft'] as $unit) {
        $quantity = $quantity->to($unit);
    }

    expect($quantity->value()->isEqualTo(1))->toBeTrue();
});

it('rounds non-terminating results to 20 significant digits by default', function () {
    expect((string) convert(1, '°F')->to('°C')->toDecimal())->toBe('-17.222222222222222222')
        ->and((string) convert(90, '°')->to('rad')->toDecimal())->toBe('1.5707963267948966192')
        ->and((string) convert(1, '°F')->to('°C')->toDecimal(2))->toBe('-17.22');
});

it('accepts enum cases and prefixed units', function () {
    expect(convert(1, Mass::Kilogram)->to(Mass::Pound)->toDecimal(5)->toString())->toBe('2.20462')
        ->and(convert(1, Length::Meter->withPrefix(Prefix::Giga))->to('km')->toString())->toBe('1000000 km')
        ->and(convert(1, DigitalStorage::Byte->withPrefix(Prefix::Tebi))->to('GiB')->toString())->toBe('1024 GiB')
        ->and(convert(1, 'kilometre')->to(Temperature::Kelvin->dimension() === Length::Meter->dimension() ? 'K' : 'm')->toString())->toBe('1000 m');
});

it('resolves prefixes on any prefixable SI unit', function (string $unit, string $to, string $expected) {
    expect(convert(1, $unit)->to($to)->toString())->toBe($expected);
})->with([
    ['Gm', 'km', '1000000 km'],
    ['gigametres', 'm', '1000000000 m'],
    ['ms', 's', '0.001 s'],
    ['MeV', 'eV', '1000000 eV'],
    ['TWh', 'GWh', '1000 GWh'],
    ['µA', 'A', '0.000001 A'],
    ['uF', 'nF', '1000 nF'],
    ['kibibytes', 'B', '1024 B'],
    ['Pibit', 'Tibit', '1024 Tibit'],
    ['decagrams', 'g', '10 g'],
    ['quettagrams', 'Rg', '1000 Rg'],
]);

it('refuses to convert between dimensions', function () {
    convert(1, 'kg')->to('m');
})->throws(IncompatibleUnitsException::class, 'Cannot convert kg (mass) to m (length)');

it('prefers the source dimension when target text is ambiguous', function () {
    expect(convert(2, 'h')->to('m')->toString())->toBe('120 min')
        ->and(convert(2, 'km')->to('m')->toString())->toBe('2000 m');
});

it('treats the gap between two temperatures as a difference, not a reading', function (string $input, string $expected) {
    expect((string) convert($input))->toBe($expected);
})->with([
    ['30 °C - 10 °C', '20 Δ°C'],
    ['30 °C - 10 °C in °F', '36 Δ°F'],
    ['20 Δ°C in Δ°F', '36 Δ°F'],
    ['20 C° in F°', '36 Δ°F'],
    ['20 °C + 9 Δ°F', '25 °C'],
    ['20 °C + 5 °C', '25 °C'],
    ['20 °C - 5 Δ°C', '15 °C'],
    ['5 Δ°C + 20 °C', '25 °C'],
    ['-40 °C - -40 °F', '0 Δ°C'],
]);

it('reads a temperature added to a temperature as a change on its own scale', function () {
    expect(convert(20, '°C')->plus(convert(9, '°F'))->toString())->toBe('25 °C')
        ->and(convert(30, Temperature::Celsius)->minus(convert(10, Temperature::Celsius))->to('°F')->toString())->toBe('36 Δ°F')
        ->and(convert('20 ± 0.5 °C')->minus(convert('10 ± 0.5 °C'))->to('Δ°F')->toString())->toBe('18.0 ± 1.8 Δ°F');
});

it('refuses to treat a difference as a reading', function () {
    convert(5, 'Δ°C')->minus(convert(20, '°C'));
})->throws(IncompatibleUnitsException::class);

it('refuses to read a temperature as a difference', function () {
    convert(20, '°C')->to('Δ°F');
})->throws(IncompatibleUnitsException::class);
