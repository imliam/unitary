<?php

declare(strict_types=1);

namespace Unitary\Color\Internal;

/**
 * Matrices and transfer functions from CSS Color Level 4 and Björn Ottosson's Oklab.
 *
 * @internal
 *
 * @phpstan-type Vector3 array{float, float, float}
 * @phpstan-type Matrix3 array{Vector3, Vector3, Vector3}
 */
final class ColorMath
{
    /** Linear sRGB to CIE XYZ (D65). */
    public const SRGB_TO_XYZ = [
        [506752 / 1228815, 87881 / 245763, 12673 / 70218],
        [87098 / 409605, 175762 / 245763, 12673 / 175545],
        [7918 / 409605, 87881 / 737289, 1001167 / 1053270],
    ];

    /** Linear Display P3 to CIE XYZ (D65). */
    public const P3_TO_XYZ = [
        [608311 / 1250200, 189793 / 714400, 198249 / 1000160],
        [35783 / 156275, 247089 / 357200, 198249 / 2500400],
        [0.0, 32229 / 714400, 5220557 / 5000800],
    ];

    /** Linear sRGB to LMS (Ottosson). */
    public const SRGB_TO_LMS = [
        [0.4122214708, 0.5363325363, 0.0514459929],
        [0.2119034982, 0.6806995451, 0.1073969566],
        [0.0883024619, 0.2817188376, 0.6299787005],
    ];

    /** Non-linear LMS to Oklab (Ottosson). */
    public const LMS_TO_OKLAB = [
        [0.2104542553, 0.7936177850, -0.0040720468],
        [1.9779984951, -2.4285922050, 0.4505937099],
        [0.0259040371, 0.7827717662, -0.8086757660],
    ];

    private const BRADFORD = [
        [0.8951, 0.2664, -0.1614],
        [-0.7502, 1.7135, 0.0367],
        [0.0389, -0.0685, 1.0296],
    ];

    public const D65 = [0.3127 / 0.3290, 1.0, (1.0 - 0.3127 - 0.3290) / 0.3290];

    public const D50 = [0.3457 / 0.3585, 1.0, (1.0 - 0.3457 - 0.3585) / 0.3585];

    /** @var array<string, Matrix3> */
    private static array $cache = [];

    /**
     * @param  Matrix3  $matrix
     * @param  Vector3  $vector
     * @return Vector3
     */
    public static function multiply(array $matrix, array $vector): array
    {
        return [
            $matrix[0][0] * $vector[0] + $matrix[0][1] * $vector[1] + $matrix[0][2] * $vector[2],
            $matrix[1][0] * $vector[0] + $matrix[1][1] * $vector[1] + $matrix[1][2] * $vector[2],
            $matrix[2][0] * $vector[0] + $matrix[2][1] * $vector[1] + $matrix[2][2] * $vector[2],
        ];
    }

    /**
     * @return Matrix3
     */
    public static function xyzToSrgb(): array
    {
        return self::$cache['xyzToSrgb'] ??= self::inverse(self::SRGB_TO_XYZ);
    }

    /**
     * @return Matrix3
     */
    public static function xyzToP3(): array
    {
        return self::$cache['xyzToP3'] ??= self::inverse(self::P3_TO_XYZ);
    }

    /**
     * @return Matrix3
     */
    public static function d65ToD50(): array
    {
        return self::$cache['d65ToD50'] ??= self::bradford(self::D65, self::D50);
    }

    /**
     * @return Matrix3
     */
    public static function d50ToD65(): array
    {
        return self::$cache['d50ToD65'] ??= self::inverse(self::d65ToD50());
    }

    /**
     * @return Matrix3
     */
    public static function xyzToLms(): array
    {
        return self::$cache['xyzToLms'] ??= self::product(self::SRGB_TO_LMS, self::xyzToSrgb());
    }

    /**
     * @return Matrix3
     */
    public static function lmsToXyz(): array
    {
        return self::$cache['lmsToXyz'] ??= self::inverse(self::xyzToLms());
    }

    /**
     * @return Matrix3
     */
    public static function oklabToLms(): array
    {
        return self::$cache['oklabToLms'] ??= self::inverse(self::LMS_TO_OKLAB);
    }

    /**
     * sRGB / Display P3 transfer function, extended symmetrically to negative values.
     */
    public static function toLinear(float $channel): float
    {
        $abs = abs($channel);
        $linear = $abs <= 0.04045 ? $abs / 12.92 : (($abs + 0.055) / 1.055) ** 2.4;

        return $channel < 0 ? -$linear : $linear;
    }

    public static function toGamma(float $channel): float
    {
        $abs = abs($channel);
        $gamma = $abs <= 0.0031308 ? $abs * 12.92 : 1.055 * $abs ** (1 / 2.4) - 0.055;

        return $channel < 0 ? -$gamma : $gamma;
    }

    /**
     * @param  Vector3  $rgb
     * @return Vector3
     */
    public static function linearise(array $rgb): array
    {
        return [self::toLinear($rgb[0]), self::toLinear($rgb[1]), self::toLinear($rgb[2])];
    }

    /**
     * @param  Vector3  $rgb
     * @return Vector3
     */
    public static function gammaEncode(array $rgb): array
    {
        return [self::toGamma($rgb[0]), self::toGamma($rgb[1]), self::toGamma($rgb[2])];
    }

    /**
     * Hue in degrees shared by HSL, HSV and HWB; 0 for achromatic colors.
     */
    public static function srgbHue(float $red, float $green, float $blue): float
    {
        $max = max($red, $green, $blue);
        $delta = $max - min($red, $green, $blue);

        if ($delta < 1e-10) {
            return 0.0;
        }

        $hue = match ($max) {
            $red => ($green - $blue) / $delta + ($green < $blue ? 6 : 0),
            $green => ($blue - $red) / $delta + 2,
            default => ($red - $green) / $delta + 4,
        };

        return self::normalizeHue($hue * 60);
    }

    public static function normalizeHue(float $hue): float
    {
        $hue = fmod($hue, 360.0);

        return $hue < 0 ? $hue + 360.0 : $hue;
    }

    /**
     * Converts rectangular a/b to [chroma, hue], treating near-zero chroma as achromatic (hue 0).
     *
     * @return array{float, float}
     */
    public static function toPolar(float $a, float $b, float $achromaticThreshold): array
    {
        $chroma = sqrt($a * $a + $b * $b);

        if ($chroma < $achromaticThreshold) {
            return [$chroma, 0.0];
        }

        return [$chroma, self::normalizeHue(rad2deg(atan2($b, $a)))];
    }

    /**
     * @return array{float, float}
     */
    public static function fromPolar(float $chroma, float $hue): array
    {
        $radians = deg2rad($hue);

        return [$chroma * cos($radians), $chroma * sin($radians)];
    }

    /**
     * @param  Matrix3  $a
     * @param  Matrix3  $b
     * @return Matrix3
     */
    public static function product(array $a, array $b): array
    {
        $row = static fn (int $i): array => [
            $a[$i][0] * $b[0][0] + $a[$i][1] * $b[1][0] + $a[$i][2] * $b[2][0],
            $a[$i][0] * $b[0][1] + $a[$i][1] * $b[1][1] + $a[$i][2] * $b[2][1],
            $a[$i][0] * $b[0][2] + $a[$i][1] * $b[1][2] + $a[$i][2] * $b[2][2],
        ];

        return [$row(0), $row(1), $row(2)];
    }

    /**
     * @param  Matrix3  $m
     * @return Matrix3
     */
    public static function inverse(array $m): array
    {
        [[$a, $b, $c], [$d, $e, $f], [$g, $h, $i]] = $m;

        $determinant = $a * ($e * $i - $f * $h) - $b * ($d * $i - $f * $g) + $c * ($d * $h - $e * $g);

        return [
            [($e * $i - $f * $h) / $determinant, ($c * $h - $b * $i) / $determinant, ($b * $f - $c * $e) / $determinant],
            [($f * $g - $d * $i) / $determinant, ($a * $i - $c * $g) / $determinant, ($c * $d - $a * $f) / $determinant],
            [($d * $h - $e * $g) / $determinant, ($b * $g - $a * $h) / $determinant, ($a * $e - $b * $d) / $determinant],
        ];
    }

    /**
     * Bradford chromatic adaptation matrix between two white points.
     *
     * @param  Vector3  $from
     * @param  Vector3  $to
     * @return Matrix3
     */
    private static function bradford(array $from, array $to): array
    {
        $coneFrom = self::multiply(self::BRADFORD, $from);
        $coneTo = self::multiply(self::BRADFORD, $to);

        $scale = [
            [$coneTo[0] / $coneFrom[0], 0.0, 0.0],
            [0.0, $coneTo[1] / $coneFrom[1], 0.0],
            [0.0, 0.0, $coneTo[2] / $coneFrom[2]],
        ];

        return self::product(self::inverse(self::BRADFORD), self::product($scale, self::BRADFORD));
    }
}
