<?php

declare(strict_types=1);

namespace Unitary\Geo\Internal;

use InvalidArgumentException;

/**
 * Gustavo Niemeyer's geohash: interleaved latitude/longitude bisections in base 32.
 *
 * @internal
 */
final class Geohash
{
    private const ALPHABET = '0123456789bcdefghjkmnpqrstuvwxyz';

    public static function encode(float $lat, float $lng, int $length): string
    {
        $latRange = [-90.0, 90.0];
        $lngRange = [-180.0, 180.0];
        $hash = '';
        $bits = 0;
        $value = 0;
        $even = true;

        while (strlen($hash) < $length) {
            $value <<= 1;

            if ($even) {
                $value |= self::bisect($lngRange, $lng);
            } else {
                $value |= self::bisect($latRange, $lat);
            }

            $even = ! $even;

            if (++$bits === 5) {
                $hash .= self::ALPHABET[$value];
                $bits = 0;
                $value = 0;
            }
        }

        return $hash;
    }

    /**
     * Halves the range towards the coordinate, returning 1 if it took the upper half.
     *
     * @param  array{float, float}  $range
     */
    private static function bisect(array &$range, float $coordinate): int
    {
        $mid = ($range[0] + $range[1]) / 2;

        if ($coordinate >= $mid) {
            $range[0] = $mid;

            return 1;
        }

        $range[1] = $mid;

        return 0;
    }

    /**
     * @return array{float, float, float, float} latitude, longitude (cell center), latitude error, longitude error
     */
    public static function decode(string $hash): array
    {
        $latRange = [-90.0, 90.0];
        $lngRange = [-180.0, 180.0];
        $even = true;

        foreach (str_split(strtolower($hash)) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index === false) {
                throw new InvalidArgumentException("Invalid geohash character \"{$char}\"");
            }

            for ($bit = 4; $bit >= 0; $bit--) {
                $on = ($index >> $bit) & 1;

                if ($even) {
                    $lngRange[$on ? 0 : 1] = ($lngRange[0] + $lngRange[1]) / 2;
                } else {
                    $latRange[$on ? 0 : 1] = ($latRange[0] + $latRange[1]) / 2;
                }

                $even = ! $even;
            }
        }

        return [
            ($latRange[0] + $latRange[1]) / 2,
            ($lngRange[0] + $lngRange[1]) / 2,
            ($latRange[1] - $latRange[0]) / 2,
            ($lngRange[1] - $lngRange[0]) / 2,
        ];
    }

    public static function isValid(string $hash): bool
    {
        return $hash !== '' && strlen($hash) <= 22 && strspn(strtolower($hash), self::ALPHABET) === strlen($hash);
    }
}
