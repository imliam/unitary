<?php

declare(strict_types=1);

namespace Unitary\Geo\Internal;

use InvalidArgumentException;

/**
 * Open Location Code ("plus codes"), following the reference implementation's
 * integer algorithm. Only full (not shortened) codes are supported.
 *
 * @see https://github.com/google/open-location-code/blob/main/docs/specification.md
 *
 * @internal
 */
final class PlusCode
{
    private const ALPHABET = '23456789CFGHJMPQRVWX';

    private const SEPARATOR_POSITION = 8;

    private const PAIR_CODE_LENGTH = 10;

    private const GRID_CODE_LENGTH = 5;

    private const GRID_ROWS = 5;

    private const GRID_COLUMNS = 4;

    private const PAIR_PRECISION = 8000; // 20^3

    private const FINAL_LAT_PRECISION = self::PAIR_PRECISION * 3125; // × GRID_ROWS^5

    private const FINAL_LNG_PRECISION = self::PAIR_PRECISION * 1024; // × GRID_COLUMNS^5

    public static function encode(float $lat, float $lng, int $length): string
    {
        if ($length < 2 || ($length < self::PAIR_CODE_LENGTH && $length % 2 === 1) || $length > 15) {
            throw new InvalidArgumentException('Plus code length must be 2, 4, 6, 8, or 10–15');
        }

        $lat = max(-90.0, min(90.0, $lat));
        $lng = fmod(fmod($lng + 180, 360) + 360, 360) - 180;

        $latValue = (int) floor(round(($lat + 90) * self::FINAL_LAT_PRECISION, 6));
        $lngValue = (int) floor(round(($lng + 180) * self::FINAL_LNG_PRECISION, 6));

        // The north pole would otherwise encode into a non-existent row.
        $latValue = min($latValue, 180 * self::FINAL_LAT_PRECISION - 1);

        $code = '';

        if ($length > self::PAIR_CODE_LENGTH) {
            for ($i = 0; $i < self::GRID_CODE_LENGTH; $i++) {
                $code = self::ALPHABET[($latValue % self::GRID_ROWS) * self::GRID_COLUMNS + $lngValue % self::GRID_COLUMNS].$code;
                $latValue = intdiv($latValue, self::GRID_ROWS);
                $lngValue = intdiv($lngValue, self::GRID_COLUMNS);
            }
        } else {
            $latValue = intdiv($latValue, 3125);
            $lngValue = intdiv($lngValue, 1024);
        }

        for ($i = 0; $i < self::PAIR_CODE_LENGTH / 2; $i++) {
            $code = self::ALPHABET[$lngValue % 20].$code;
            $code = self::ALPHABET[$latValue % 20].$code;
            $latValue = intdiv($latValue, 20);
            $lngValue = intdiv($lngValue, 20);
        }

        if ($length < self::SEPARATOR_POSITION) {
            return substr($code, 0, $length).str_repeat('0', self::SEPARATOR_POSITION - $length).'+';
        }

        return substr($code, 0, self::SEPARATOR_POSITION).'+'.substr($code, self::SEPARATOR_POSITION, $length - self::SEPARATOR_POSITION);
    }

    /**
     * @return array{float, float} latitude and longitude of the code area's center
     */
    public static function decode(string $code): array
    {
        if (! self::isValidFull($code)) {
            throw new InvalidArgumentException("Invalid full plus code \"{$code}\"");
        }

        $digits = str_replace(['+', '0'], '', strtoupper($code));
        $digits = substr($digits, 0, 15);

        $latValue = 0;
        $lngValue = 0;
        $latPlace = 20 ** 4 * 3125; // value of the first pair digit in final-precision units
        $lngPlace = 20 ** 4 * 1024;
        $latSize = $latPlace;
        $lngSize = $lngPlace;

        $pairDigits = min(strlen($digits), self::PAIR_CODE_LENGTH);

        for ($i = 0; $i < $pairDigits; $i += 2) {
            $latValue += strpos(self::ALPHABET, $digits[$i]) * $latPlace;
            $lngValue += strpos(self::ALPHABET, $digits[$i + 1] ?? '2') * $lngPlace;
            $latSize = $latPlace;
            $lngSize = $lngPlace;

            if ($i < self::PAIR_CODE_LENGTH - 2) {
                $latPlace = intdiv($latPlace, 20);
                $lngPlace = intdiv($lngPlace, 20);
            }
        }

        $latPlace = 3125;
        $lngPlace = 1024;

        for ($i = self::PAIR_CODE_LENGTH; $i < strlen($digits); $i++) {
            $latPlace = intdiv($latPlace, self::GRID_ROWS);
            $lngPlace = intdiv($lngPlace, self::GRID_COLUMNS);
            $index = (int) strpos(self::ALPHABET, $digits[$i]);
            $latValue += intdiv($index, self::GRID_COLUMNS) * $latPlace;
            $lngValue += ($index % self::GRID_COLUMNS) * $lngPlace;
            $latSize = $latPlace;
            $lngSize = $lngPlace;
        }

        return [
            ($latValue + $latSize / 2) / self::FINAL_LAT_PRECISION - 90,
            ($lngValue + $lngSize / 2) / self::FINAL_LNG_PRECISION - 180,
        ];
    }

    public static function isValidFull(string $code): bool
    {
        $code = strtoupper($code);

        if (! preg_match('/^[23456789CFGHJMPQRVWX]{2}(?:[23456789CFGHJMPQRVWX]{6}|[23456789CFGHJMPQRVWX]{4}00|[23456789CFGHJMPQRVWX]{2}0000|000000)\+(?:[23456789CFGHJMPQRVWX]{2,7})?$/', $code)) {
            return false;
        }

        // Padded codes can't have digits after the separator.
        if (str_contains(substr($code, 0, 8), '0') && ! str_ends_with($code, '+')) {
            return false;
        }

        // The first latitude digit can't exceed 180° and the first longitude digit 360°.
        return strpos(self::ALPHABET, $code[0]) < 9 && strpos(self::ALPHABET, $code[1]) < 18;
    }
}
