<?php

declare(strict_types=1);

use Unitary\Color\ColorSpace;
use Unitary\Exceptions\ParseException;

it('resolves color space names and aliases', function (string $name, ColorSpace $expected) {
    expect(ColorSpace::fromName($name))->toBe($expected)
        ->and(ColorSpace::tryFromName($name))->toBe($expected);
})->with([
    ['hex', ColorSpace::Hex],
    ['rgb', ColorSpace::Rgb],
    ['RGBA', ColorSpace::Rgb],
    ['srgb', ColorSpace::Rgb],
    ['linear-rgb', ColorSpace::LinearRgb],
    ['srgb-linear', ColorSpace::LinearRgb],
    ['linear rgb', ColorSpace::LinearRgb],
    ['hsl', ColorSpace::Hsl],
    ['hsv', ColorSpace::Hsv],
    ['hsb', ColorSpace::Hsv],
    ['hwb', ColorSpace::Hwb],
    ['cmyk', ColorSpace::Cmyk],
    ['xyz', ColorSpace::Xyz],
    ['xyz-d65', ColorSpace::Xyz],
    ['XYZ_D50', ColorSpace::XyzD50],
    ['lab', ColorSpace::Lab],
    ['lch', ColorSpace::Lch],
    ['oklab', ColorSpace::Oklab],
    [' OkLCh ', ColorSpace::Oklch],
    ['p3', ColorSpace::DisplayP3],
    ['display-p3', ColorSpace::DisplayP3],
]);

it('rejects unknown color spaces', function () {
    expect(ColorSpace::tryFromName('pantone'))->toBeNull()
        ->and(fn () => ColorSpace::fromName('pantone'))->toThrow(ParseException::class);
});

it('maps every space to a color class of that space', function (ColorSpace $space) {
    $class = $space->colorClass();

    expect(is_subclass_of($class, \Unitary\Color\Color::class))->toBeTrue()
        ->and(\Unitary\Color\Color::parse('red')->to($space))->toBeInstanceOf($class)
        ->and($space->label())->not->toBeEmpty();
})->with(ColorSpace::cases());
