<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Quantity;
use Unitary\Scales\WireGauge;
use Unitary\Units\Length;

it('follows the ASTM B258 formula', function (int|string $gauge, string $diameterMm) {
    expect(WireGauge::of($gauge)->diameter()->toDecimal(3)->toString())->toBe($diameterMm);
})->with([
    [36, '0.127'],       // 0.005 in by definition
    ['4/0', '11.684'],   // 0.46 in by definition
    ['0000', '11.684'],
    [10, '2.588'],
    [12, '2.053'],
    [14, '1.628'],
    [40, '0.080'],
]);

it('computes cross-sectional area', function () {
    // 10 AWG ≈ 5.26 mm²
    expect(WireGauge::of(10)->crossSectionalArea()->toDecimal(2)->toString())->toBe('5.26');
});

it('parses freeform gauges', function (string $input, string $expected) {
    expect(WireGauge::tryParse($input)?->toString())->toBe($expected);
})->with([
    ['12 AWG', '12 AWG'],
    ['12awg', '12 AWG'],
    ['AWG 12', '12 AWG'],
    ['#12 gauge', '12 AWG'],
    ['4/0 AWG', '4/0 AWG'],
    ['00 awg', '2/0 AWG'],
]);

it('finds the nearest gauge to a diameter', function () {
    expect(WireGauge::fromDiameter(new Quantity('2.6', Length::Millimeter))->toString())->toBe('10 AWG')
        ->and(WireGauge::fromDiameter(new Quantity(1, Length::Inch))->toString())->toBe('4/0 AWG');
});

it('rejects gauges outside 4/0–40', function () {
    expect(WireGauge::tryParse('50 AWG'))->toBeNull()
        ->and(WireGauge::tryParse('12'))->toBeNull()
        ->and(fn () => WireGauge::of(41))->toThrow(ParseException::class);
});
