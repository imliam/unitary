<?php

declare(strict_types=1);

use Unitary\Color\Cmyk;
use Unitary\Color\Color;
use Unitary\Color\ColorSpace;
use Unitary\Color\DisplayP3;
use Unitary\Color\Hex;
use Unitary\Color\Hsl;
use Unitary\Color\Hsv;
use Unitary\Color\Hwb;
use Unitary\Color\Internal\NamedColors;
use Unitary\Color\Lab;
use Unitary\Color\Lch;
use Unitary\Color\LinearRgb;
use Unitary\Color\Oklab;
use Unitary\Color\Oklch;
use Unitary\Color\Rgb;
use Unitary\Color\Xyz;
use Unitary\Color\XyzD50;
use Unitary\Exceptions\ParseException;

it('parses hex notations', function (string $input, string $expected) {
    $color = Color::parse($input);

    expect($color)->toBeInstanceOf(Hex::class)
        ->and($color->toString())->toBe($expected);
})->with([
    ['#abc', '#aabbcc'],
    ['#ABC', '#aabbcc'],
    ['#abcd', '#aabbccdd'],
    ['#ff0000', '#ff0000'],
    ['  #FF0000  ', '#ff0000'],
    ['#ff000080', '#ff000080'],
]);

it('reads hex alpha', function () {
    expect(Color::parse('#ff000080')->alpha())->toEqualWithDelta(0.502, 0.001)
        ->and(Color::parse('#f00f')->alpha())->toBe(1.0);
});

it('parses functional notations', function (string $input, string $class, string $expected) {
    $color = Color::parse($input);

    expect($color)->toBeInstanceOf($class)
        ->and($color->toHex()->toString())->toBe($expected);
})->with([
    ['rgb(255 0 0)', Rgb::class, '#ff0000'],
    ['rgb(255, 0, 0)', Rgb::class, '#ff0000'],
    ['RGB(255,0,0)', Rgb::class, '#ff0000'],
    ['rgb(100% 0% 0%)', Rgb::class, '#ff0000'],
    ['rgba(255, 0, 0, 0.5)', Rgb::class, '#ff000080'],
    ['rgb(255 0 0 / 50%)', Rgb::class, '#ff000080'],
    ['rgb(none 128 0)', Rgb::class, '#008000'],
    ['hsl(120 100% 25%)', Hsl::class, '#008000'],
    ['hsl(120deg, 100%, 25%)', Hsl::class, '#008000'],
    ['hsla(120, 100%, 25%, 1)', Hsl::class, '#008000'],
    ['hsl(0.5turn 100% 50%)', Hsl::class, '#00ffff'],
    ['hsl(200grad 100% 50%)', Hsl::class, '#00ffff'],
    ['hsl(3.14159265rad 100% 50%)', Hsl::class, '#00ffff'],
    ['hsl(120 100 25)', Hsl::class, '#008000'],
    ['hsv(0 100% 100%)', Hsv::class, '#ff0000'],
    ['hsb(240, 100%, 100%)', Hsv::class, '#0000ff'],
    ['hwb(0 0% 0%)', Hwb::class, '#ff0000'],
    ['hwb(0 50% 50%)', Hwb::class, '#808080'],
    ['cmyk(0% 100% 100% 0%)', Cmyk::class, '#ff0000'],
    ['cmyk(0, 1, 1, 0)', Cmyk::class, '#ff0000'],
    ['device-cmyk(0 0 0 1)', Cmyk::class, '#000000'],
    ['lab(54.29% 80.8 69.89)', Lab::class, '#ff0000'],
    ['lab(54.29 64.64% 55.912%)', Lab::class, '#ff0000'],
    ['lch(54.29% 106.84 40.85)', Lch::class, '#ff0000'],
    ['oklab(62.8% 0.2249 0.1258)', Oklab::class, '#ff0000'],
    ['oklab(0.628 0.2249 0.1258)', Oklab::class, '#ff0000'],
    ['oklch(62.8% 0.2577 29.23)', Oklch::class, '#ff0000'],
    ['oklch(0.628 64.43% 29.23deg)', Oklch::class, '#ff0000'],
    ['color(srgb 1 0 0)', Rgb::class, '#ff0000'],
    ['color(srgb 100% 0% 0%)', Rgb::class, '#ff0000'],
    ['color(srgb-linear 1 0 0)', LinearRgb::class, '#ff0000'],
    ['color(display-p3 0.9175 0.2003 0.1386)', DisplayP3::class, '#ff0000'],
    ['color(xyz 0.41239 0.21264 0.01933)', Xyz::class, '#ff0000'],
    ['color(xyz-d65 0.41239 0.21264 0.01933)', Xyz::class, '#ff0000'],
    ['color(xyz-d50 0.43607 0.22249 0.01392)', XyzD50::class, '#ff0000'],
]);

it('parses alpha in modern and legacy syntax', function (string $input, float $alpha) {
    expect(Color::parse($input)->alpha())->toBe($alpha);
})->with([
    ['hsl(0 100% 50% / 0.3)', 0.3],
    ['hsla(0, 100%, 50%, 30%)', 0.3],
    ['oklch(62.8% 0.2577 29.23 / 0.75)', 0.75],
    ['color(display-p3 1 0 0 / 0.5)', 0.5],
    ['cmyk(0% 100% 100% 0% / 0.5)', 0.5],
    ['rgb(255 0 0 / 2)', 1.0],
]);

it('parses every css named color', function () {
    expect(NamedColors::COLORS)->toHaveCount(148);

    foreach (NamedColors::COLORS as $name => $hex) {
        expect(Color::parse($name)->toString())->toBe("#{$hex}")
            ->and(Color::parse(strtoupper($name))->toString())->toBe("#{$hex}");
    }
});

it('parses transparent', function () {
    $color = Color::parse('transparent');

    expect($color->alpha())->toBe(0.0)->and($color->toString())->toBe('#00000000');
});

it('round trips its own string output', function (ColorSpace $space) {
    $color = Color::parse('#663399cc')->to($space);
    $reparsed = Color::parse($color->toString());

    expect($reparsed->space())->toBe($space === ColorSpace::Hex ? ColorSpace::Hex : $space)
        ->and($reparsed->equals($color))->toBeTrue();
})->with(ColorSpace::cases());

it('rejects invalid input', function (string $input) {
    expect(Color::tryParse($input))->toBeNull()
        ->and(fn () => Color::parse($input))->toThrow(ParseException::class);
})->with([
    '',
    'ff0000',
    '123',
    '#ff000',
    '#gggggg',
    'notacolor',
    'rgb(255 0)',
    'rgb(255 0 0 0 0)',
    'rgb(a b c)',
    'rgb(255px 0 0)',
    'hsl(10% 100% 50%)',
    'rgb(1 2 3 / 4 / 5)',
    'color(rec2020 1 0 0)',
    'fancy(1 2 3)',
    '23 kg',
]);

it('explains why parsing failed', function () {
    expect(fn () => Color::parse('rgb(255 0)'))
        ->toThrow(ParseException::class, 'Unable to parse "rgb(255 0)" as a color: expected 3 channels, got 2');
});
