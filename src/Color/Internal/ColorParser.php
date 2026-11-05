<?php

declare(strict_types=1);

namespace Unitary\Color\Internal;

use Unitary\Color\Cmyk;
use Unitary\Color\Color;
use Unitary\Color\DisplayP3;
use Unitary\Color\Hex;
use Unitary\Color\Hsl;
use Unitary\Color\Hsv;
use Unitary\Color\Hwb;
use Unitary\Color\Lab;
use Unitary\Color\Lch;
use Unitary\Color\LinearRgb;
use Unitary\Color\Oklab;
use Unitary\Color\Oklch;
use Unitary\Color\Rgb;
use Unitary\Color\Xyz;
use Unitary\Color\XyzD50;
use Unitary\Exceptions\ParseException;

/**
 * Parses CSS color syntax: hex, named colors, and functional notations (modern and legacy).
 *
 * @internal
 */
final class ColorParser
{
    private const TOKEN = '/^([+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(%|deg|rad|grad|turn)?$/';

    /**
     * @throws ParseException
     */
    public static function parse(string $input): Color
    {
        $value = strtolower(trim($input));

        if ($value === 'transparent') {
            return new Hex(0, 0, 0, 0.0);
        }

        if (isset(NamedColors::COLORS[$value])) {
            return Hex::fromString(NamedColors::COLORS[$value]);
        }

        if (str_starts_with($value, '#')) {
            try {
                return Hex::fromString($value);
            } catch (ParseException) {
                throw ParseException::cannotParse($input, 'color', 'invalid hex notation');
            }
        }

        if (preg_match('/^([a-z][a-z0-9-]*)\s*\((.*)\)$/s', $value, $matches) !== 1) {
            throw ParseException::cannotParse($input, 'color');
        }

        [, $function, $body] = $matches;

        try {
            return self::parseFunction($function, $body);
        } catch (ParseException $exception) {
            throw ParseException::cannotParse($input, 'color', $exception->getMessage());
        }
    }

    private static function parseFunction(string $function, string $body): Color
    {
        if ($function === 'color') {
            [$tokens, $alpha] = self::tokens($body, 4);
            $space = array_shift($tokens);
            [$a, $b, $c] = array_map(static fn (string $token): float => self::number($token, 1), $tokens);

            return match ($space) {
                'srgb' => new Rgb($a * 255, $b * 255, $c * 255, $alpha),
                'srgb-linear' => new LinearRgb($a, $b, $c, $alpha),
                'display-p3' => new DisplayP3($a, $b, $c, $alpha),
                'xyz', 'xyz-d65' => new Xyz($a, $b, $c, $alpha),
                'xyz-d50' => new XyzD50($a, $b, $c, $alpha),
                default => throw new ParseException("unsupported color() space \"{$space}\""),
            };
        }

        if ($function === 'cmyk' || $function === 'device-cmyk') {
            [[$c, $m, $y, $k], $alpha] = self::tokens($body, 4);
            $ink = static fn (string $token): float => str_ends_with($token, '%') ? self::number($token, 100) : self::number($token, 1) * 100;

            return new Cmyk($ink($c), $ink($m), $ink($y), $ink($k), $alpha);
        }

        [[$first, $second, $third], $alpha] = self::tokens($body, 3);

        return match ($function) {
            'rgb', 'rgba' => new Rgb(self::number($first, 255), self::number($second, 255), self::number($third, 255), $alpha),
            'hsl', 'hsla' => new Hsl(self::hue($first), self::number($second, 100), self::number($third, 100), $alpha),
            'hsv', 'hsb', 'hsva', 'hsba' => new Hsv(self::hue($first), self::number($second, 100), self::number($third, 100), $alpha),
            'hwb' => new Hwb(self::hue($first), self::number($second, 100), self::number($third, 100), $alpha),
            'lab' => new Lab(self::number($first, 100), self::number($second, 125), self::number($third, 125), $alpha),
            'lch' => new Lch(self::number($first, 100), self::number($second, 150), self::hue($third), $alpha),
            'oklab' => new Oklab(self::number($first, 1), self::number($second, 0.4), self::number($third, 0.4), $alpha),
            'oklch' => new Oklch(self::number($first, 1), self::number($second, 0.4), self::hue($third), $alpha),
            default => throw new ParseException("unknown color function \"{$function}()\""),
        };
    }

    /**
     * Splits a function body into exactly $count channel tokens, extracting alpha from a "/ a"
     * suffix or a trailing legacy comma-separated argument.
     *
     * @return array{list<string>, float}
     */
    private static function tokens(string $body, int $count): array
    {
        $parts = explode('/', $body);

        if (count($parts) > 2) {
            throw new ParseException('too many "/" separators');
        }

        $tokens = preg_split('/[\s,]+/', trim($parts[0]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $alpha = isset($parts[1]) ? trim($parts[1]) : null;

        if ($alpha === null && count($tokens) === $count + 1) {
            $alpha = array_pop($tokens);
        }

        if (count($tokens) !== $count) {
            throw new ParseException(sprintf('expected %d channels, got %d', $count, count($tokens)));
        }

        return [$tokens, $alpha === null ? 1.0 : max(0.0, min(1.0, self::number($alpha, 1)))];
    }

    /**
     * A plain number or a percentage, where 100% maps to $percentScale. "none" is zero.
     */
    private static function number(string $token, float $percentScale): float
    {
        if ($token === 'none') {
            return 0.0;
        }

        if (preg_match(self::TOKEN, $token, $matches) !== 1 || (isset($matches[2]) && ! in_array($matches[2], ['', '%'], true))) {
            throw new ParseException("invalid value \"{$token}\"");
        }

        $number = (float) $matches[1];

        return isset($matches[2]) && $matches[2] === '%' ? $number / 100 * $percentScale : $number;
    }

    private static function hue(string $token): float
    {
        if ($token === 'none') {
            return 0.0;
        }

        if (preg_match(self::TOKEN, $token, $matches) !== 1) {
            throw new ParseException("invalid hue \"{$token}\"");
        }

        $number = (float) $matches[1];

        return match ($matches[2] ?? '') {
            '', 'deg' => $number,
            'rad' => rad2deg($number),
            'grad' => $number * 0.9,
            'turn' => $number * 360,
            default => throw new ParseException("invalid hue \"{$token}\""),
        };
    }
}
