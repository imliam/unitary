<?php

declare(strict_types=1);

namespace Unitary\Geo\Internal;

/**
 * Vincenty's inverse solution on the WGS84 ellipsoid, after T. Vincenty (1975),
 * "Direct and Inverse Solutions of Geodesics on the Ellipsoid".
 *
 * @internal
 */
final class Vincenty
{
    /**
     * @return array{distance: float, bearing: float}|null Meters and initial bearing in degrees, or null if it fails to converge (near-antipodal points).
     */
    public static function inverse(float $lat1, float $lng1, float $lat2, float $lng2): ?array
    {
        $a = Ellipsoid::A;
        $b = Ellipsoid::B;
        $f = Ellipsoid::F;

        $L = deg2rad($lng2 - $lng1);
        $tanU1 = (1 - $f) * tan(deg2rad($lat1));
        $cosU1 = 1 / sqrt(1 + $tanU1 ** 2);
        $sinU1 = $tanU1 * $cosU1;
        $tanU2 = (1 - $f) * tan(deg2rad($lat2));
        $cosU2 = 1 / sqrt(1 + $tanU2 ** 2);
        $sinU2 = $tanU2 * $cosU2;

        $lambda = $L;
        $sinLambda = $cosLambda = $sinSigma = $cosSigma = $sigma = $cosSqAlpha = $cos2SigmaM = 0.0;
        $antipodal = abs($L) > M_PI_2 || abs($lat2 - $lat1) > 90;

        for ($i = 0; $i < 1000; $i++) {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);
            $sinSqSigma = ($cosU2 * $sinLambda) ** 2 + ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) ** 2;

            if (abs($sinSqSigma) < 1e-24) {
                return ['distance' => 0.0, 'bearing' => 0.0]; // coincident points
            }

            $sinSigma = sqrt($sinSqSigma);
            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);
            $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
            $cosSqAlpha = 1 - $sinAlpha ** 2;
            $cos2SigmaM = $cosSqAlpha !== 0.0 ? $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha : 0.0; // equatorial line
            $C = $f / 16 * $cosSqAlpha * (4 + $f * (4 - 3 * $cosSqAlpha));
            $previous = $lambda;
            $lambda = $L + (1 - $C) * $f * $sinAlpha * ($sigma + $C * $sinSigma * ($cos2SigmaM + $C * $cosSigma * (-1 + 2 * $cos2SigmaM ** 2)));

            if (($antipodal ? abs($lambda) - M_PI : abs($lambda)) > M_PI) {
                return null;
            }

            if (abs($lambda - $previous) <= 1e-12) {
                break;
            }
        }

        if ($i >= 1000) {
            return null;
        }

        $uSq = $cosSqAlpha * ($a ** 2 - $b ** 2) / ($b ** 2);
        $A = 1 + $uSq / 16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $B = $uSq / 1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));
        $deltaSigma = $B * $sinSigma * ($cos2SigmaM + $B / 4 * ($cosSigma * (-1 + 2 * $cos2SigmaM ** 2)
            - $B / 6 * $cos2SigmaM * (-3 + 4 * $sinSigma ** 2) * (-3 + 4 * $cos2SigmaM ** 2)));

        $bearing = rad2deg(atan2($cosU2 * $sinLambda, $cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda));

        return ['distance' => $b * $A * ($sigma - $deltaSigma), 'bearing' => fmod($bearing + 360, 360)];
    }

    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $h = sin(($phi2 - $phi1) / 2) ** 2 + cos($phi1) * cos($phi2) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 2 * Ellipsoid::MEAN_RADIUS * atan2(sqrt($h), sqrt(1 - $h));
    }

    /** Initial great-circle bearing in degrees. */
    public static function sphericalBearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $deltaLambda = deg2rad($lng2 - $lng1);
        $y = sin($deltaLambda) * cos($phi2);
        $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($deltaLambda);

        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }
}
