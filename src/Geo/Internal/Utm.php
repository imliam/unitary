<?php

declare(strict_types=1);

namespace Unitary\Geo\Internal;

use InvalidArgumentException;

/**
 * Universal Transverse Mercator on WGS84 using Krüger's n-series to n⁶
 * (Karney 2011, "Transverse Mercator with an accuracy of a few nanometers"),
 * including the Norway and Svalbard zone exceptions.
 *
 * @internal
 */
final class Utm
{
    private const K0 = 0.9996;

    private const FALSE_EASTING = 500000.0;

    private const FALSE_NORTHING = 10000000.0;

    /** Latitude band letters from 80°S, each 8° tall (X is 12°). */
    public const BANDS = 'CDEFGHJKLMNPQRSTUVWXX';

    /**
     * @return array{zone: int, band: string, easting: float, northing: float}
     */
    public static function fromLatLng(float $lat, float $lng): array
    {
        if ($lat < -80 || $lat > 84) {
            throw new InvalidArgumentException('UTM is only defined between 80°S and 84°N');
        }

        $zone = (int) floor(($lng + 180) / 6) + 1;
        $zone = $zone > 60 ? 60 : $zone;

        // Norway and Svalbard exceptions.
        if ($lat >= 56 && $lat < 64 && $lng >= 3 && $lng < 12) {
            $zone = 32;
        }

        if ($lat >= 72) {
            $zone = match (true) {
                $lng >= 0 && $lng < 9 => 31,
                $lng >= 9 && $lng < 21 => 33,
                $lng >= 21 && $lng < 33 => 35,
                $lng >= 33 && $lng < 42 => 37,
                default => $zone,
            };
        }

        $band = self::BANDS[(int) floor($lat / 8 + 10)];
        [$easting, $northing] = self::project($lat, $lng, $zone);

        return ['zone' => $zone, 'band' => $band, 'easting' => $easting, 'northing' => $northing];
    }

    /**
     * @return array{float, float} easting and northing in meters
     */
    public static function project(float $lat, float $lng, int $zone): array
    {
        [$e, $n, $A] = self::constants();
        $alpha = self::alpha($n);

        $phi = deg2rad($lat);
        $lambda = deg2rad($lng - self::centralMeridian($zone));
        $cosLambda = cos($lambda);
        $sinLambda = sin($lambda);

        $tau = tan($phi);
        $sigma = sinh($e * atanh($e * $tau / sqrt(1 + $tau ** 2)));
        $tauPrime = $tau * sqrt(1 + $sigma ** 2) - $sigma * sqrt(1 + $tau ** 2);

        $xiPrime = atan2($tauPrime, $cosLambda);
        $etaPrime = asinh($sinLambda / sqrt($tauPrime ** 2 + $cosLambda ** 2));

        $xi = $xiPrime;
        $eta = $etaPrime;

        foreach ($alpha as $j => $coefficient) {
            $xi += $coefficient * sin(2 * $j * $xiPrime) * cosh(2 * $j * $etaPrime);
            $eta += $coefficient * cos(2 * $j * $xiPrime) * sinh(2 * $j * $etaPrime);
        }

        $easting = self::K0 * $A * $eta + self::FALSE_EASTING;
        $northing = self::K0 * $A * $xi;

        if ($northing < 0) {
            $northing += self::FALSE_NORTHING;
        }

        return [$easting, $northing];
    }

    /**
     * @return array{float, float} latitude and longitude in degrees
     */
    public static function toLatLng(int $zone, bool $northern, float $easting, float $northing): array
    {
        if ($zone < 1 || $zone > 60) {
            throw new InvalidArgumentException("Invalid UTM zone {$zone}");
        }

        [$e, $n, $A] = self::constants();
        $beta = self::beta($n);

        $x = $easting - self::FALSE_EASTING;
        $y = $northern ? $northing : $northing - self::FALSE_NORTHING;

        $eta = $x / (self::K0 * $A);
        $xi = $y / (self::K0 * $A);

        $xiPrime = $xi;
        $etaPrime = $eta;

        foreach ($beta as $j => $coefficient) {
            $xiPrime -= $coefficient * sin(2 * $j * $xi) * cosh(2 * $j * $eta);
            $etaPrime -= $coefficient * cos(2 * $j * $xi) * sinh(2 * $j * $eta);
        }

        $sinhEtaPrime = sinh($etaPrime);
        $sinXiPrime = sin($xiPrime);
        $cosXiPrime = cos($xiPrime);
        $tauPrime = $sinXiPrime / sqrt($sinhEtaPrime ** 2 + $cosXiPrime ** 2);

        $tau = $tauPrime;

        for ($i = 0; $i < 100; $i++) {
            $sigma = sinh($e * atanh($e * $tau / sqrt(1 + $tau ** 2)));
            $tauI = $tau * sqrt(1 + $sigma ** 2) - $sigma * sqrt(1 + $tau ** 2);
            $delta = ($tauPrime - $tauI) / sqrt(1 + $tauI ** 2)
                * (1 + (1 - $e ** 2) * $tau ** 2) / ((1 - $e ** 2) * sqrt(1 + $tau ** 2));
            $tau += $delta;

            if (abs($delta) < 1e-12) {
                break;
            }
        }

        $lat = rad2deg(atan($tau));
        $lng = self::centralMeridian($zone) + rad2deg(atan2($sinhEtaPrime, $cosXiPrime));

        return [$lat, $lng];
    }

    public static function centralMeridian(int $zone): float
    {
        return ($zone - 1) * 6 - 180 + 3;
    }

    /**
     * @return array{float, float, float} eccentricity, third flattening, rectifying radius
     */
    private static function constants(): array
    {
        $f = Ellipsoid::F;
        $e = sqrt($f * (2 - $f));
        $n = $f / (2 - $f);
        $A = Ellipsoid::A / (1 + $n) * (1 + $n ** 2 / 4 + $n ** 4 / 64 + $n ** 6 / 256);

        return [$e, $n, $A];
    }

    /**
     * @return array<int, float>
     */
    private static function alpha(float $n): array
    {
        return [
            1 => 1 / 2 * $n - 2 / 3 * $n ** 2 + 5 / 16 * $n ** 3 + 41 / 180 * $n ** 4 - 127 / 288 * $n ** 5 + 7891 / 37800 * $n ** 6,
            2 => 13 / 48 * $n ** 2 - 3 / 5 * $n ** 3 + 557 / 1440 * $n ** 4 + 281 / 630 * $n ** 5 - 1983433 / 1935360 * $n ** 6,
            3 => 61 / 240 * $n ** 3 - 103 / 140 * $n ** 4 + 15061 / 26880 * $n ** 5 + 167603 / 181440 * $n ** 6,
            4 => 49561 / 161280 * $n ** 4 - 179 / 168 * $n ** 5 + 6601661 / 7257600 * $n ** 6,
            5 => 34729 / 80640 * $n ** 5 - 3418889 / 1995840 * $n ** 6,
            6 => 212378941 / 319334400 * $n ** 6,
        ];
    }

    /**
     * @return array<int, float>
     */
    private static function beta(float $n): array
    {
        return [
            1 => 1 / 2 * $n - 2 / 3 * $n ** 2 + 37 / 96 * $n ** 3 - 1 / 360 * $n ** 4 - 81 / 512 * $n ** 5 + 96199 / 604800 * $n ** 6,
            2 => 1 / 48 * $n ** 2 + 1 / 15 * $n ** 3 - 437 / 1440 * $n ** 4 + 46 / 105 * $n ** 5 - 1118711 / 3870720 * $n ** 6,
            3 => 17 / 480 * $n ** 3 - 37 / 840 * $n ** 4 - 209 / 4480 * $n ** 5 + 5569 / 90720 * $n ** 6,
            4 => 4397 / 161280 * $n ** 4 - 11 / 504 * $n ** 5 - 830251 / 7257600 * $n ** 6,
            5 => 4583 / 161280 * $n ** 5 - 108847 / 3991680 * $n ** 6,
            6 => 20648693 / 638668800 * $n ** 6,
        ];
    }
}
