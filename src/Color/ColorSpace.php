<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Exceptions\ParseException;

enum ColorSpace: string
{
    case Hex = 'hex';
    case Rgb = 'rgb';
    case LinearRgb = 'srgb-linear';
    case Hsl = 'hsl';
    case Hsv = 'hsv';
    case Hwb = 'hwb';
    case Cmyk = 'cmyk';
    case Xyz = 'xyz-d65';
    case XyzD50 = 'xyz-d50';
    case Lab = 'lab';
    case Lch = 'lch';
    case Oklab = 'oklab';
    case Oklch = 'oklch';
    case DisplayP3 = 'display-p3';

    private const ALIASES = [
        'hex' => self::Hex, '#' => self::Hex,
        'rgb' => self::Rgb, 'rgba' => self::Rgb, 'srgb' => self::Rgb,
        'srgb-linear' => self::LinearRgb, 'linear-rgb' => self::LinearRgb, 'linear-srgb' => self::LinearRgb, 'linearrgb' => self::LinearRgb,
        'hsl' => self::Hsl, 'hsla' => self::Hsl,
        'hsv' => self::Hsv, 'hsb' => self::Hsv,
        'hwb' => self::Hwb,
        'cmyk' => self::Cmyk, 'device-cmyk' => self::Cmyk,
        'xyz' => self::Xyz, 'xyz-d65' => self::Xyz, 'ciexyz' => self::Xyz,
        'xyz-d50' => self::XyzD50,
        'lab' => self::Lab, 'cielab' => self::Lab,
        'lch' => self::Lch, 'cielch' => self::Lch,
        'oklab' => self::Oklab,
        'oklch' => self::Oklch,
        'display-p3' => self::DisplayP3, 'p3' => self::DisplayP3, 'displayp3' => self::DisplayP3,
    ];

    /**
     * @throws ParseException
     */
    public static function fromName(string $name): self
    {
        $key = (string) preg_replace('/[\s_]+/', '-', strtolower(trim($name)));

        return self::ALIASES[$key] ?? throw ParseException::cannotParse($name, 'color space');
    }

    public static function tryFromName(string $name): ?self
    {
        $key = (string) preg_replace('/[\s_]+/', '-', strtolower(trim($name)));

        return self::ALIASES[$key] ?? null;
    }

    /**
     * @return class-string<Color>
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Hex => Hex::class,
            self::Rgb => Rgb::class,
            self::LinearRgb => LinearRgb::class,
            self::Hsl => Hsl::class,
            self::Hsv => Hsv::class,
            self::Hwb => Hwb::class,
            self::Cmyk => Cmyk::class,
            self::Xyz => Xyz::class,
            self::XyzD50 => XyzD50::class,
            self::Lab => Lab::class,
            self::Lch => Lch::class,
            self::Oklab => Oklab::class,
            self::Oklch => Oklch::class,
            self::DisplayP3 => DisplayP3::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Hex => 'Hex',
            self::Rgb => 'sRGB',
            self::LinearRgb => 'Linear sRGB',
            self::Hsl => 'HSL',
            self::Hsv => 'HSV',
            self::Hwb => 'HWB',
            self::Cmyk => 'CMYK',
            self::Xyz => 'CIE XYZ (D65)',
            self::XyzD50 => 'CIE XYZ (D50)',
            self::Lab => 'CIELAB',
            self::Lch => 'CIELCh',
            self::Oklab => 'Oklab',
            self::Oklch => 'OkLCh',
            self::DisplayP3 => 'Display P3',
        };
    }
}
