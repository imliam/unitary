<?php

declare(strict_types=1);

use Unitary\Color\Cmyk;
use Unitary\Color\Color;
use Unitary\Color\ColorSpace;
use Unitary\Color\Hex;
use Unitary\Color\Hsl;
use Unitary\Color\Lab;
use Unitary\Color\Oklch;
use Unitary\Color\Rgb;

dataset('red in every space', [
    'hex' => [ColorSpace::Hex, '#ff0000'],
    'rgb' => [ColorSpace::Rgb, 'rgb(255 0 0)'],
    'linear rgb' => [ColorSpace::LinearRgb, 'color(srgb-linear 1 0 0)'],
    'hsl' => [ColorSpace::Hsl, 'hsl(0 100% 50%)'],
    'hsv' => [ColorSpace::Hsv, 'hsv(0 100% 100%)'],
    'hwb' => [ColorSpace::Hwb, 'hwb(0 0% 0%)'],
    'cmyk' => [ColorSpace::Cmyk, 'cmyk(0% 100% 100% 0%)'],
    'xyz d65' => [ColorSpace::Xyz, 'color(xyz-d65 0.41239 0.21264 0.01933)'],
    'xyz d50' => [ColorSpace::XyzD50, 'color(xyz-d50 0.43607 0.22249 0.01392)'],
    'lab' => [ColorSpace::Lab, 'lab(54.29% 80.8 69.89)'],
    'oklab' => [ColorSpace::Oklab, 'oklab(62.8% 0.2249 0.1258)'],
    'oklch' => [ColorSpace::Oklch, 'oklch(62.8% 0.2577 29.23)'],
    'display p3' => [ColorSpace::DisplayP3, 'color(display-p3 0.9175 0.2003 0.1386)'],
]);

it('converts red to every space', function (ColorSpace $space, string $expected) {
    $converted = Color::parse('#ff0000')->to($space);

    expect($converted->space())->toBe($space)
        ->and($converted->toString())->toBe($expected);
})->with('red in every space');

it('round trips red back to hex from every space', function (ColorSpace $space, string $css) {
    expect(Color::parse($css)->toHex()->toString())->toBe('#ff0000');
})->with('red in every space');

it('converts red to lch', function () {
    $lch = Color::parse('red')->toLch();

    expect($lch->lightness)->toEqualWithDelta(54.29, 0.01)
        ->and($lch->chroma)->toEqualWithDelta(106.84, 0.01)
        ->and($lch->hue)->toEqualWithDelta(40.85, 0.02);
});

it('round trips a range of colors through every space', function (string $hex) {
    $color = Hex::fromString($hex);

    foreach (ColorSpace::cases() as $space) {
        $back = $color->to($space)->toHex();

        expect($back->toString())->toBe($hex, "via {$space->value}");
    }
})->with(['#000000', '#ffffff', '#808080', '#123456', '#abcdef', '#663399', '#00ff7f', '#fa8072', '#010203']);

it('maps white to the reference white point', function () {
    $white = Color::parse('white');

    expect($white->toLab()->toString())->toBe('lab(100% 0 0)')
        ->and($white->toOklch()->toString())->toBe('oklch(100% 0 0)')
        ->and($white->toXyz()->toString())->toBe('color(xyz-d65 0.95046 1 1.08906)')
        ->and($white->toXyzD50()->toString())->toBe('color(xyz-d50 0.9643 1 0.8251)');
});

it('gives achromatic colors a hue of zero', function () {
    $grey = Color::parse('#777777');

    expect($grey->toHsl()->hue)->toBe(0.0)
        ->and($grey->toHsl()->saturation)->toBe(0.0)
        ->and($grey->toHsv()->hue)->toBe(0.0)
        ->and($grey->toLch()->hue)->toBe(0.0)
        ->and($grey->toOklch()->hue)->toBe(0.0);
});

it('converts between hue-based spaces', function () {
    expect(Hsl::of(120, 100, 25)->toHex()->toString())->toBe('#008000')
        ->and(Hsl::of(240, 100, 50)->toHsv()->toString())->toBe('hsv(240 100% 100%)')
        ->and(Hsl::of(60, 100, 50)->toHwb()->toString())->toBe('hwb(60 0% 0%)')
        ->and(Hsl::of(480, 100, 50)->toHex()->toString())->toBe('#00ff00')
        ->and(Hsl::of(-120, 100, 50)->toHex()->toString())->toBe('#0000ff');
});

it('converts to naive cmyk', function () {
    expect(Color::parse('black')->toCmyk()->toString())->toBe('cmyk(0% 0% 0% 100%)')
        ->and(Color::parse('white')->toCmyk()->toString())->toBe('cmyk(0% 0% 0% 0%)')
        ->and(Color::parse('#336699')->toCmyk()->toString())->toBe('cmyk(66.67% 33.33% 0% 40%)')
        ->and(Cmyk::of(66.67, 33.33, 0, 40)->toHex()->toString())->toBe('#336699');
});

it('preserves out of gamut values except in hex', function () {
    $rgb = Color::parse('color(display-p3 1 0 0)')->toRgb();

    expect($rgb->isInGamut())->toBeFalse()
        ->and($rgb->red)->toBeGreaterThan(255)
        ->and($rgb->green)->toBeLessThan(0)
        ->and($rgb->clamp()->isInGamut())->toBeTrue()
        ->and($rgb->toHex()->toString())->toBe('#ff0000')
        ->and(Color::parse('red')->toDisplayP3()->isInGamut())->toBeTrue();
});

it('carries alpha through conversions', function () {
    $color = Color::parse('rgb(255 0 0 / 0.25)');

    expect($color->toOklch()->alpha)->toBe(0.25)
        ->and($color->toOklch()->toString())->toBe('oklch(62.8% 0.2577 29.23 / 0.25)')
        ->and($color->toHex()->toString())->toBe('#ff000040')
        ->and($color->toHsl()->toString())->toBe('hsl(0 100% 50% / 0.25)');
});

it('accepts color space names in to()', function () {
    expect(Color::parse('red')->to('OKLCH'))->toBeInstanceOf(Oklch::class)
        ->and(Color::parse('red')->to('hsb')->space())->toBe(ColorSpace::Hsv)
        ->and(Color::parse('red')->to('p3')->space())->toBe(ColorSpace::DisplayP3);
});

it('returns the same instance when converting to its own space', function () {
    $rgb = Rgb::of(1, 2, 3);

    expect($rgb->toRgb())->toBe($rgb)->and($rgb->to(ColorSpace::Rgb))->toBe($rgb);
});

it('compares colors with a tolerance', function () {
    expect(Color::parse('red')->equals(Color::parse('oklch(62.8% 0.2577 29.23)')))->toBeTrue()
        ->and(Color::parse('red')->equals(Color::parse('#fe0000')))->toBeFalse()
        ->and(Color::parse('red')->equals(Color::parse('#fe0000'), tolerance: 1.0))->toBeTrue()
        ->and(Color::parse('red')->equals(Color::parse('rgb(255 0 0 / 0.5)')))->toBeFalse();
});

it('finds css names', function () {
    expect(Color::parse('#663399')->toName())->toBe('rebeccapurple')
        ->and(Color::parse('hsl(0 100% 50%)')->toName())->toBe('red')
        ->and(Color::parse('#00ffff')->toName())->toBe('aqua')
        ->and(Color::parse('#123456')->toName())->toBeNull()
        ->and(Color::parse('transparent')->toName())->toBe('transparent');
});

it('serialises to json', function () {
    expect(json_encode(Color::parse('red')->toHsl()))
        ->toBe('{"space":"hsl","hue":0,"saturation":100,"lightness":50,"alpha":1}')
        ->and(Color::parse('#336699')->jsonSerialize())
        ->toBe(['space' => 'hex', 'red' => 51, 'green' => 102, 'blue' => 153, 'alpha' => 1.0]);
});

it('is stringable', function () {
    expect((string) Lab::of(50, 0, 0))->toBe('lab(50% 0 0)');
});
