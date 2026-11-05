<?php

declare(strict_types=1);

use Unitary\Quantity;
use Unitary\Scales\GasMark;
use Unitary\Scales\PaperSize;
use Unitary\Scales\Scales;
use Unitary\Scales\ShoeSize;

it('converts freeform scale inputs', function (string $source, string $target, string $expected) {
    expect((string) Scales::tryConvert($source, $target))->toBe($expected);
})->with([
    ['UK 9 shoe', 'EU', 'EU 43'],
    ['UK9 shoes', 'US', 'US 10'],
    ['size 9 US mens shoe', 'UK', 'UK 8'],
    ['UK 6 womens shoe', "US women's", 'US 8.5 (women)'],
    ['EU 43 shoe', 'UK', 'UK 9'],
    ['UK 9 shoe', 'cm', '27.093333333333333333 cm'],
    ['27 cm', 'UK shoe size', 'UK 9'],
    ['ring size N', 'US', 'US 6.75'],
    ['US ring size 7', 'UK', 'UK N½'],
    ['ring size 7', 'mm', '17.3228 mm'],
    ['17.3228 mm', 'US ring size', 'US 7'],
    ['A4', 'in', '8.27 × 11.69 in'],
    ['A4', 'inches', '8.27 × 11.69 in'],
    ['Letter', 'mm', '215.9 × 279.4 mm'],
    ['A0', 'm²', '0.999949 m²'],
    ['12 AWG', 'mm', '2.052525388493947616 mm'],
    ['10 AWG', 'mm²', '5.261154954510373 mm²'],
    ['2.6 mm', 'AWG', '10 AWG'],
    ['gas mark 4', '°F', '350 °F'],
    ['200 °C', 'gas mark', 'Gas mark 6'],
    ['400 °F', 'gas mark', 'Gas mark 6'],
    ['beaufort 6', 'm/s', '12.28664054980042 m/s'],
    ['20 mph', 'beaufort', 'Beaufort 5 (Fresh breeze)'],
    ['40 knots', 'Beaufort scale', 'Beaufort 8 (Gale)'],
]);

it('parses scales on their own', function () {
    expect(Scales::tryParse('UK 9 shoe'))->toBeInstanceOf(ShoeSize::class)
        ->and(Scales::tryParse('A4'))->toBeInstanceOf(PaperSize::class)
        ->and(Scales::tryParse('gas mark 5'))->toBeInstanceOf(GasMark::class)
        ->and(Scales::tryConvert('12 AWG', 'mm'))->toBeInstanceOf(Quantity::class);
});

it('leaves ordinary quantities and other inputs alone', function (string $source, string $target) {
    expect(Scales::tryConvert($source, $target))->toBeNull();
})->with([
    ['5 ft', 'EU'],
    ['£23', 'USD'],
    ['5 km', 'mi'],
    ['9 shoes', 'EU'],
    ['size 9', 'EU'],
    ['ring', 'US'],
    ['red', 'hex'],
    ['A4', 'kg'],
    ['gas mark 4', 'mph'],
    ['20 kg', 'beaufort'],
]);

it('does not parse plain quantities or words as scales', function (string $source) {
    expect(Scales::tryParse($source))->toBeNull();
})->with(['5 ft', '23', '#fff', 'red', '12', 'A', 'force', 'gas']);
