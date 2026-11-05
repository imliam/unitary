<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Parsing\RomanNumerals;

it('parses canonical numerals', function (string $numeral, int $expected) {
    expect(RomanNumerals::toInt($numeral))->toBe($expected);
})->with([
    ['I', 1], ['IV', 4], ['IX', 9], ['XIV', 14], ['XL', 40], ['XC', 90], ['CD', 400],
    ['CM', 900], ['MCMXCIV', 1994], ['MMXXVI', 2026], ['MMMCMXCIX', 3999], ['xiv', 14],
]);

it('rejects non-canonical or invalid numerals', function (string $numeral) {
    expect(RomanNumerals::tryToInt($numeral))->toBeNull();
})->with([[''], ['IIII'], ['VX'], ['IC'], ['MMMM'], ['ABC'], ['Xiv'], ['IIV'], ['VV']]);

it('throws for invalid numerals', function () {
    RomanNumerals::toInt('IIII');
})->throws(ParseException::class);

it('formats integers and round-trips', function () {
    expect(RomanNumerals::fromInt(1994))->toBe('MCMXCIV')
        ->and(RomanNumerals::fromInt(3999))->toBe('MMMCMXCIX');

    foreach (range(1, 3999) as $value) {
        expect(RomanNumerals::toInt(RomanNumerals::fromInt($value)))->toBe($value);
    }
});

it('refuses values outside 1 to 3999', function (int $value) {
    RomanNumerals::fromInt($value);
})->with([0, -1, 4000])->throws(InvalidArgumentException::class);
