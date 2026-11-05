<?php

declare(strict_types=1);

namespace Unitary\Geo\Internal;

use InvalidArgumentException;

/**
 * Recognizes coordinates written as decimal degrees, DMS, DDM, UTM, MGRS,
 * "geohash:…" and plus codes. Deliberately conservative: bare pairs of numbers
 * need decimal points, and everything else needs degree symbols or hemispheres,
 * so quantities like "5 ft 11 in" or "13' 11\"" are never mistaken for positions.
 *
 * @internal
 */
final class CoordinateParser
{
    private const DEGREE = '°|º|˚|deg(?:rees?)?(?![a-z])|d(?![a-z])';

    private const MINUTE = "′|’|‘|'(?!')|min(?:utes?)?(?![a-z])|m(?![a-z])";

    private const SECOND = "″|”|“|\"|''|sec(?:onds?)?(?![a-z])|s(?![a-z])";

    /**
     * @return array{float, float}|null latitude and longitude
     */
    public static function parse(string $input): ?array
    {
        $text = trim(str_replace(['−', "\u{00A0}"], ['-', ' '], $input));

        if ($text === '') {
            return null;
        }

        try {
            return self::geohash($text)
                ?? self::plusCode($text)
                ?? self::mgrs($text)
                ?? self::utm($text)
                ?? self::decimalPair($text)
                ?? self::angles($text, self::symbolPattern())
                ?? self::angles($text, self::spacedPattern(hemisphereFirst: true), requireHemispheres: true)
                ?? self::angles($text, self::spacedPattern(hemisphereFirst: false), requireHemispheres: true);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return array{float, float}|null
     */
    private static function geohash(string $text): ?array
    {
        if (! preg_match('/^geo\s?hash\s*[:=]?\s*([0-9a-z]+)$/i', $text, $matches) || ! Geohash::isValid($matches[1])) {
            return null;
        }

        [$lat, $lng] = Geohash::decode($matches[1]);

        return [$lat, $lng];
    }

    /**
     * @return array{float, float}|null
     */
    private static function plusCode(string $text): ?array
    {
        $code = (string) preg_replace('/^(?:plus\s?code|olc|open location code)\s*[:=]?\s*/i', '', $text);

        return str_contains($code, '+') && PlusCode::isValidFull($code) ? PlusCode::decode($code) : null;
    }

    /**
     * @return array{float, float}|null
     */
    private static function mgrs(string $text): ?array
    {
        if (! preg_match('/^(\d{1,2})\s*([C-HJ-NP-X])\s*([A-HJ-NP-Z])\s*([A-HJ-NP-V])\s*(\d+)(?:\s+(\d+))?$/i', $text, $m)) {
            return null;
        }

        if (isset($m[6])) {
            [$easting, $northing] = [$m[5], $m[6]];
        } else {
            $half = intdiv(strlen($m[5]), 2);
            [$easting, $northing] = [substr($m[5], 0, $half), substr($m[5], $half)];
        }

        if ($easting === '' || strlen($easting) !== strlen($northing)) {
            return null;
        }

        return Mgrs::toLatLng((int) $m[1], $m[2], $m[3], $m[4], $easting, $northing);
    }

    /**
     * @return array{float, float}|null
     */
    private static function utm(string $text): ?array
    {
        if (! preg_match('/^(\d{1,2})\s*([C-HJ-NP-X])\s+(\d{6}(?:\.\d+)?)\s*(?:m\s*)?E?\s*,?\s+(\d{1,8}(?:\.\d+)?)\s*(?:m\s*)?N?$/i', $text, $m)) {
            return null;
        }

        $northing = (float) $m[4];

        if ($northing > 10000000) {
            return null;
        }

        return Utm::toLatLng((int) $m[1], strtoupper($m[2]) >= 'N', (float) $m[3], $northing);
    }

    /**
     * @return array{float, float}|null
     */
    private static function decimalPair(string $text): ?array
    {
        if (! preg_match('/^([-+]?\d{1,3}\.\d+)\s*(?:[,;]\s*|\s+)([-+]?\d{1,3}\.\d+)$/', $text, $m)) {
            return null;
        }

        return self::validate((float) $m[1], (float) $m[2]);
    }

    /**
     * @return array{float, float}|null
     */
    private static function angles(string $text, string $pattern, bool $requireHemispheres = false): ?array
    {
        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $first = self::component($m, '1');
        $second = self::component($m, '2');

        if ($first === null || $second === null) {
            return null;
        }

        if (! $first['marked'] && ! $second['marked']) {
            return null;
        }

        if ($requireHemispheres && ($first['hemisphere'] === null || $second['hemisphere'] === null)) {
            return null;
        }

        $firstIsLongitude = in_array($first['hemisphere'], ['E', 'W'], true);
        $secondIsLatitude = in_array($second['hemisphere'], ['N', 'S'], true);

        if ($firstIsLongitude || $secondIsLatitude) {
            if (! $firstIsLongitude && $first['hemisphere'] !== null || ! $secondIsLatitude && $second['hemisphere'] !== null) {
                return null; // both latitudes or both longitudes
            }

            [$first, $second] = [$second, $first];
        } elseif (in_array($first['hemisphere'], ['N', 'S'], true) && in_array($second['hemisphere'], ['N', 'S'], true)) {
            return null;
        }

        return self::validate($first['value'], $second['value']);
    }

    /**
     * @param  array<array-key, string>  $m
     * @return array{value: float, hemisphere: ?string, marked: bool}|null
     */
    private static function component(array $m, string $i): ?array
    {
        $degrees = $m["deg{$i}"];
        $minutes = $m["min{$i}"] ?? '';
        $seconds = $m["sec{$i}"] ?? '';
        $before = strtoupper($m["hp{$i}"] ?? '');
        $after = strtoupper($m["hs{$i}"] ?? '');

        if ($before !== '' && $after !== '') {
            return null;
        }

        if ($minutes !== '' && str_contains($degrees, '.') || $seconds !== '' && str_contains($minutes, '.')) {
            return null; // only the last part may have a fraction
        }

        if ($minutes !== '' && (float) $minutes >= 60 || $seconds !== '' && (float) $seconds >= 60) {
            return null;
        }

        $hemisphere = $before !== '' ? $before : ($after !== '' ? $after : null);
        $value = (float) $degrees + (float) $minutes / 60 + (float) $seconds / 3600;
        $negative = ($m["sign{$i}"] ?? '') === '-';

        if ($negative && in_array($hemisphere, ['S', 'W'], true)) {
            return null;
        }

        if ($negative || in_array($hemisphere, ['S', 'W'], true)) {
            $value = -$value;
        }

        $marked = $hemisphere !== null || ($m["ds{$i}"] ?? '') !== '' || ($m["ms{$i}"] ?? '') !== '';

        return ['value' => $value, 'hemisphere' => $hemisphere, 'marked' => $marked];
    }

    /**
     * @return array{float, float}|null
     */
    private static function validate(float $lat, float $lng): ?array
    {
        return abs($lat) <= 90 && abs($lng) <= 180 ? [$lat, $lng] : null;
    }

    private static function symbolPattern(): string
    {
        $component = fn (string $i) => "(?<hp{$i}>(?-i:[NSEW]))?\\s*(?<sign{$i}>[-+])?\\s*(?<deg{$i}>\\d{1,3}(?:\\.\\d+)?)"
            ."(?:\\s*(?<ds{$i}>".self::DEGREE.'))?'
            ."(?:(?(<ds{$i}>)\\s*|\\s+)(?<min{$i}>\\d{1,2}(?:\\.\\d+)?)\\s*(?<ms{$i}>".self::MINUTE.')'
            ."(?:\\s*(?<sec{$i}>\\d{1,2}(?:\\.\\d+)?)\\s*(?<ss{$i}>".self::SECOND.'))?)?'
            ."(?:\\s*(?<hs{$i}>(?-i:[NSEW]))(?![a-z]))?";

        return '/^'.$component('1').'\s*(?:[,;\/]\s*)?'.$component('2').'$/iu';
    }

    /** "N 51 30 26 W 0 7 39" or "51 30 26 N 0 7 39 W": no symbols, so hemispheres are required. */
    private static function spacedPattern(bool $hemisphereFirst): string
    {
        $component = function (string $i) use ($hemisphereFirst): string {
            $number = "(?<sign{$i}>-)?(?<deg{$i}>\\d{1,3}(?:\\.\\d+)?)(?:\\s+(?<min{$i}>\\d{1,2}(?:\\.\\d+)?)(?:\\s+(?<sec{$i}>\\d{1,2}(?:\\.\\d+)?))?)?";

            return $hemisphereFirst ? "(?<hp{$i}>[NSEW])\\s*{$number}" : "{$number}\\s*(?<hs{$i}>[NSEW])";
        };

        return '/^'.$component('1').'\s*,?\s*'.$component('2').'$/u';
    }
}
