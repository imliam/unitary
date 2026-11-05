<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Quantity;
use Unitary\Scales\RingSize;
use Unitary\Scales\RingSizeSystem;
use Unitary\Units\Length;

it('uses the US formula of 0.458 in + 0.032 in per size', function () {
    // US 7: 0.458 + 7 × 0.032 = 0.682 in = 17.3228 mm
    expect(RingSize::of(7)->diameter()->toString())->toBe('17.3228 mm')
        ->and(RingSize::of(0)->diameter()->to(Length::Inch)->toString())->toBe('0.458 in');
});

it('uses BS 6820 letters for UK sizes', function () {
    // A = 37.5 mm circumference, +1.25 mm per letter: N = 53.75 mm
    expect(RingSize::of('A', 'UK')->circumference()->toString())->toBe('37.5 mm')
        ->and(RingSize::of('N')->circumference()->toString())->toBe('53.75 mm')
        ->and(RingSize::of('N½')->circumference()->toString())->toBe('54.375 mm')
        ->and(RingSize::of('n 1/2')->toString())->toBe('UK N½');
});

it('converts between systems via the inner circumference', function (string|int $size, ?string $system, string $to, string $expected) {
    expect(RingSize::of($size, $system)->to($to)->toString())->toBe($expected);
})->with([
    [7, 'US', 'UK', 'UK N½'],
    [7, 'US', 'ISO', 'ISO 54.5'],
    ['N', null, 'US', 'US 6.75'],
    [54, 'ISO', 'US', 'US 6.75'],
    ['P', 'UK', 'US', 'US 7.75'],
]);

it('builds from a measured diameter', function () {
    expect(RingSize::fromDiameter(new Quantity('17.3228', Length::Millimeter), RingSizeSystem::US)->toString())->toBe('US 7');
});

it('rejects mismatched notation', function () {
    expect(fn () => RingSize::of(7, 'UK'))->toThrow(ParseException::class)
        ->and(fn () => RingSize::of('N', 'US'))->toThrow(ParseException::class)
        ->and(fn () => RingSize::of('large'))->toThrow(ParseException::class);
});
