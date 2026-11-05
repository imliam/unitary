<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;

use function Unitary\convert;

it('converts logarithmic units exactly where possible', function (string $input, string $expected) {
    expect(convert($input)->toString())->toBe($expected);
})->with([
    ['20 dBm to mW', '100 mW'],
    ['100 mW to dBm', '20 dBm'],
    ['30 dBm in W', '1 W'],
    ['0 dBW in dBm', '30 dBm'],
    ['3 dB to ratio', '1.9952623149688796014'],
    ['2 times in dB', '3.0102999566398119521 dB'],
    ['1 Np in dB', '8.685889638065036553 dB'],
    ['1 octave in cents', '1200 cent'],
    ['12 semitones to octaves', '1 oct'],
    ['7 pH in mol/L', '0.0000001 M'],
    ['0.001 M in pH', '3 pH'],
    ['94 dB SPL in Pa', '1.00237446725454457 Pa'],
    ['0 dBu in V', '0.77459666924148337704 V'],
]);

it('refuses to add logarithmic units', function () {
    convert('5 dBm + 3 dBm');
})->throws(ParseException::class);

it('round-trips very large and very small logarithmic values', function () {
    expect(convert('123.456 pH to mol/L')->to('pH')->toString())->toBe('123.456 pH')
        ->and(convert('-120 dBm to W')->toScientific(4))->toBe('1e-15 W');
});
