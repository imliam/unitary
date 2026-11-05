<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Scales\ShoeFit;
use Unitary\Scales\ShoeSize;
use Unitary\Scales\ShoeSizeSystem;
use Unitary\Units\Length;

it('converts adult sizes between systems using the barleycorn and Paris point formulas', function (string $from, ShoeSizeSystem $system, ShoeSizeSystem $to, ShoeFit $fit, string $expected) {
    expect(ShoeSize::of($from, $system, $fit)->to($to)->toString())->toBe($expected);
})->with([
    // UK = 3L − 25, US men = 3L − 24, so US men = UK + 1
    ['9', ShoeSizeSystem::UK, ShoeSizeSystem::US, ShoeFit::Men, 'US 10'],
    ['10', ShoeSizeSystem::US, ShoeSizeSystem::UK, ShoeFit::Men, 'UK 9'],
    // US women = 3L − 22.5, so US women = UK + 2.5
    ['6', ShoeSizeSystem::UK, ShoeSizeSystem::US, ShoeFit::Women, 'US 8.5 (women)'],
    // UK 9 last = 34/3 in = 287.87 mm, EU = 1.5 × 28.787 = 43.18 → 43
    ['9', ShoeSizeSystem::UK, ShoeSizeSystem::EU, ShoeFit::Men, 'EU 43'],
    // EU 42 last = 28 cm = 11.02 in → UK 8.07 → 8
    ['42', ShoeSizeSystem::EU, ShoeSizeSystem::UK, ShoeFit::Men, 'UK 8'],
    ['9', ShoeSizeSystem::UK, ShoeSizeSystem::Mondopoint, ShoeFit::Men, 'Mondopoint 270'],
    ['9', ShoeSizeSystem::UK, ShoeSizeSystem::JP, ShoeFit::Men, 'JP 27'],
    // Children: UK child = 3L − 12, US child = 3L − 11⅔
    ['10', ShoeSizeSystem::UK, ShoeSizeSystem::US, ShoeFit::Children, 'US 10.5 (children)'],
]);

it('exposes last and foot lengths', function () {
    $size = ShoeSize::of(9, ShoeSizeSystem::UK);

    expect((string) $size->lastLength()->to(Length::Inch)->value())->toBe('34/3')
        ->and($size->footLength()->to(Length::Millimeter)->toDecimal(2)->toString())->toBe('270.93')
        ->and(ShoeSize::of(0, 'UK', ShoeFit::Children)->lastLength()->to(Length::Inch)->toString())->toBe('4 in');
});

it('rounds to each system\'s step and accepts half sizes', function () {
    expect(ShoeSize::of('9½', 'UK')->toString())->toBe('UK 9.5')
        ->and(ShoeSize::of('9 1/2', 'uk')->to('EU')->toString())->toBe('EU 44')
        ->and(ShoeSize::fromFootLength(new Unitary\Quantity(263, Length::Millimeter), ShoeSizeSystem::Mondopoint)->toString())->toBe('Mondopoint 265');
});

it('rejects invalid sizes and systems', function () {
    expect(fn () => ShoeSize::of('big', 'UK'))->toThrow(ParseException::class)
        ->and(fn () => ShoeSize::of(9, 'Narnia'))->toThrow(ParseException::class);
});

it('serializes', function () {
    expect(json_encode(ShoeSize::of(9, 'UK')))->toBe('{"system":"UK","size":"9","fit":"men","foot_length_mm":"270.9"}');
});
