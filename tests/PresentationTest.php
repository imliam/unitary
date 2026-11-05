<?php

declare(strict_types=1);

use Unitary\UnitSystem;

use function Unitary\convert;

it('humanizes into readable units within the same system', function (string $input, string $expected) {
    expect(convert($input)->humanize()->format(2, thousandsSeparator: ''))->toBe($expected);
})->with([
    ['0.00002 m', '20.00 µm'],
    ['123456 m', '123.46 km'],
    ['12345 W', '12.35 kW'],
    ['0.002 A', '2.00 mA'],
    ['1234567890 B', '1.23 GB'],
    ['3 GiB', '3.00 GiB'],
    ['90000 s', '1.04 d'],
    ['90 s', '1.50 min'],
    ['30 in', '2.50 ft'],
    ['3 tsp', '1.00 tbsp'],
    ['6 tsp', '1.00 fl oz'],
    ['250000 Pa', '250.00 kPa'],
]);

it('switches unit systems', function () {
    expect(convert('1.83 m')->toImperial()->format(2))->toBe('6.00 ft')
        ->and(convert('70 °F')->toMetric()->format(1))->toBe('21.1 °C')
        ->and(convert('100 km/h')->toImperial()->format(1))->toBe('62.1 mph')
        ->and(convert('4 L')->toUsCustomary()->format(2))->toBe('1.06 gal')
        ->and(convert('4 L')->humanize(UnitSystem::Imperial)->format(2))->toBe('7.04 imp pt')
        ->and(convert('30 mpg')->toMetric()->format(2))->toBe('7.84 L/100km');
});

it('breaks quantities down into parts', function () {
    expect(convert('1.83 m')->breakdown('ft', 'in')->toString(0))->toBe('6 ft')
        ->and(convert('1.80 m')->breakdown('ft', 'in')->toString(0))->toBe('5 ft 11 in')
        ->and(convert('100000 s')->breakdown()->toString())->toBe('1 d 3 h 46 min 40 s')
        ->and(convert('71 in')->breakdown()->toString())->toBe('5 ft 11 in')
        ->and(convert('-71 in')->breakdown()->toString())->toBe('-5 ft 11 in')
        ->and(convert('51.5072°')->breakdown()->toString(0))->toBe('51° 30′ 26″')
        ->and(convert('3.5 lb')->breakdown()->toString())->toBe('3 lb 8 oz')
        ->and(json_encode(convert('90 min')->breakdown('h', 'min')))->toBe('[{"value":"1","unit":"h","unitName":"hour","dimension":"time"},{"value":"30","unit":"min","unitName":"minute","dimension":"time"}]');
});

it('carries rounding into larger parts', function () {
    expect(convert('71.99 in')->breakdown('ft', 'in')->toString(0))->toBe('6 ft');
});

it('tracks uncertainty through conversions and arithmetic', function () {
    expect(convert('5.0 ± 0.1 m')->toString())->toBe('5.00 ± 0.10 m')
        ->and(convert('5.0 ± 0.1 m in ft')->toString())->toBe('16.40 ± 0.33 ft')
        ->and(convert('(5.0 ± 0.1) m')->toString())->toBe('5.00 ± 0.10 m')
        ->and(convert('5 m ± 10 cm')->toString())->toBe('5.00 ± 0.10 m')
        ->and(convert('5 m +/- 0.1')->toString())->toBe('5.00 ± 0.10 m')
        ->and(convert('2.0 ± 0.1 m')->plus(convert('3.0 ± 0.2 m'))->toString())->toBe('5.00 ± 0.30 m')
        ->and(convert('2.0 ± 0.1 m')->multipliedBy(convert('3.0 ± 0.2 m'))->toString())->toBe('6.00 ± 0.70 m²')
        ->and(convert('2.0 ± 0.1 m')->power(2)->toString())->toBe('4.00 ± 0.40 m²')
        ->and(convert('100 ± 1 °C to °F')->toString())->toBe('212.0 ± 1.8 °F')
        ->and(json_encode(convert('5 ± 0.5 kg')))->toBe('{"value":"5","unit":"kg","unitName":"kilogram","dimension":"mass","uncertainty":"0.5"}');
});

it('tracks significant figures from the written value', function () {
    expect(convert('2.50 kg')->significantFigures())->toBe(3)
        ->and(convert('0.0250 kg')->significantFigures())->toBe(3)
        ->and(convert('2500 kg')->significantFigures())->toBe(2)
        ->and(convert('1.00e3 kg')->significantFigures())->toBe(3)
        ->and(convert('1/3 cup')->significantFigures())->toBeNull()
        ->and(convert('2.50 kg in lb')->toPrecision())->toBe('5.51 lb')
        ->and(convert('2.50 kg in lb')->roundToSignificantFigures()->toString())->toBe('5.51 lb')
        ->and(convert('2.5 m')->multipliedBy(convert('3.14159 m'))->significantFigures())->toBe(2)
        ->and(convert(2.5, 'm')->significantFigures())->toBeNull()
        ->and(convert('2.50', 'm')->significantFigures())->toBe(3);
});
