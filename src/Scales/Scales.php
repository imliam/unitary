<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Unitary\Dimension;
use Unitary\Exceptions\UnitaryException;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\Unitary;

/**
 * Freeform entry point for scales, used by the converter once ordinary
 * quantity parsing has failed:
 *
 *     Scales::tryParse('UK 9 shoe')                  // ShoeSize
 *     Scales::tryConvert('UK 9 shoe', 'EU')          // ShoeSize EU 43
 *     Scales::tryConvert('A4', 'in')                 // PaperSize 8.27 × 11.69 in
 *     Scales::tryConvert('12 AWG', 'mm')             // Quantity 2.05 mm
 *     Scales::tryConvert('200 °C', 'gas mark')       // GasMark 6
 *
 * Parsing is deliberately strict (shoe and ring sizes need the word "shoe" or
 * "ring") so ordinary quantities are never mistaken for scales.
 */
final class Scales
{
    public static function tryParse(string $source): ?Scale
    {
        $source = trim($source);

        return self::shoe($source)
            ?? self::ring($source)
            ?? WireGauge::tryParse($source)
            ?? GasMark::tryParse($source)
            ?? Beaufort::tryParse($source)
            ?? PaperSize::tryParse($source);
    }

    /**
     * @return Scale|\Unitary\Quantity<\Unitary\Unit>|null
     */
    public static function tryConvert(string $source, string $target): Scale|Quantity|null
    {
        $target = trim($target);
        $scale = self::tryParse($source);

        if ($scale !== null) {
            return self::convertScale($scale, $target);
        }

        $quantity = self::quantity($source);

        return $quantity === null ? null : self::toScale($quantity, $target);
    }

    /**
     * @return Scale|\Unitary\Quantity<\Unitary\Unit>|null
     */
    private static function convertScale(Scale $scale, string $target): Scale|Quantity|null
    {
        return match (true) {
            $scale instanceof ShoeSize => self::shoeTarget($target, $scale)
                ?? self::inUnit($scale->footLength(), $target, Dimension::Length),
            $scale instanceof RingSize => (($system = RingSizeSystem::tryFromName(self::strip($target, 'ring', 'size'))) !== null ? $scale->to($system) : null)
                ?? self::inUnit($scale->diameter(), $target, Dimension::Length),
            $scale instanceof PaperSize => (self::unit($target, Dimension::Length) !== null ? $scale->in($target) : null)
                ?? self::inUnit($scale->area(), $target, Dimension::Area),
            $scale instanceof WireGauge => self::inUnit($scale->diameter(), $target, Dimension::Length)
                ?? self::inUnit($scale->crossSectionalArea(), $target, Dimension::Area),
            $scale instanceof GasMark => self::inUnit($scale->temperature(), $target, Dimension::Temperature),
            $scale instanceof Beaufort => self::inUnit($scale->speed(), $target, Dimension::Speed),
            default => null,
        };
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $quantity
     */
    private static function toScale(Quantity $quantity, string $target): ?Scale
    {
        $words = strtolower(self::strip($target, 'size', 'scale', 'number'));

        return match (true) {
            $quantity->dimension() === Dimension::Temperature && preg_match('/^gas(\s*mark)?$/', $words) === 1 => GasMark::fromTemperature($quantity),
            $quantity->dimension() === Dimension::Speed && preg_match('/^(beaufort|bft|wind force)$/', $words) === 1 => Beaufort::fromSpeed($quantity),
            $quantity->dimension() === Dimension::Length && preg_match('/^(awg|wire\s*gauge|gauge)$/', $words) === 1 => WireGauge::fromDiameter($quantity),
            $quantity->dimension() === Dimension::Length && str_contains($words, 'ring')
                && ($system = RingSizeSystem::tryFromName(self::strip($words, 'ring'))) !== null => RingSize::fromDiameter($quantity, $system),
            $quantity->dimension() === Dimension::Length && str_contains($words, 'shoe') => self::shoeTarget($words, null, $quantity),
            default => null,
        };
    }

    /**
     * Reads a shoe target such as "EU", "US women's" or "UK kids" and converts
     * either an existing size or a foot length to it.
     *
     * @param  ?\Unitary\Quantity<\Unitary\Unit>  $foot
     */
    private static function shoeTarget(string $target, ?ShoeSize $from, ?Quantity $foot = null): ?ShoeSize
    {
        [$system, $fit, $rest] = self::shoeTokens(self::strip($target, 'shoe', 'shoes', 'size', 'sizes'));

        if ($system === null || $rest !== []) {
            return null;
        }

        if ($from !== null) {
            return $from->to($system, $fit);
        }

        return $foot !== null ? ShoeSize::fromFootLength($foot, $system, $fit ?? ShoeFit::Men) : null;
    }

    private static function shoe(string $source): ?ShoeSize
    {
        if (preg_match('/\bshoes?\b/i', $source) !== 1) {
            return null;
        }

        [$system, $fit, $rest] = self::shoeTokens(self::strip($source, 'shoe', 'shoes', 'size', 'sz'));
        $number = $rest === [] ? null : NumberParser::tryParse(implode(' ', $rest));

        if ($system === null || $number === null) {
            return null;
        }

        return ShoeSize::of((string) $number, $system, $fit ?? ShoeFit::Men);
    }

    /**
     * @return array{?ShoeSizeSystem, ?ShoeFit, list<string>}
     */
    private static function shoeTokens(string $text): array
    {
        $system = null;
        $fit = null;
        $rest = [];

        foreach (self::tokens($text) as $token) {
            if ($fit === null && ($candidate = ShoeFit::tryFromName($token)) !== null) {
                $fit = $candidate;
            } elseif ($system === null && ($candidate = ShoeSizeSystem::tryFromName($token)) !== null) {
                $system = $candidate;
            } else {
                $rest[] = $token;
            }
        }

        return [$system, $fit, $rest];
    }

    private static function ring(string $source): ?RingSize
    {
        if (preg_match('/\bring\b/i', $source) !== 1) {
            return null;
        }

        $system = null;
        $rest = [];

        foreach (self::tokens(self::strip($source, 'ring', 'size')) as $token) {
            if ($system === null && ($candidate = RingSizeSystem::tryFromName($token)) !== null) {
                $system = $candidate;
            } else {
                $rest[] = $token;
            }
        }

        if ($rest === []) {
            return null;
        }

        try {
            return RingSize::of(implode(' ', $rest), $system);
        } catch (UnitaryException) {
            return null;
        }
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $quantity
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    private static function inUnit(Quantity $quantity, string $target, Dimension $dimension): ?Quantity
    {
        $unit = self::unit($target, $dimension);

        return $unit === null ? null : $quantity->to($unit);
    }

    private static function unit(string $target, Dimension $dimension): ?Unit
    {
        $unit = Unitary::converter()->units()->tryResolve($target, $dimension);

        return $unit?->dimension() === $dimension ? $unit : null;
    }

    /**
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    private static function quantity(string $source): ?Quantity
    {
        try {
            return Unitary::converter()->parseQuantity($source);
        } catch (UnitaryException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $text): array
    {
        // "UK9" => "UK 9", "9UK" => "9 UK"
        $text = (string) preg_replace('/(?<=\p{L})(?=\d)|(?<=\d)(?=\p{L})/u', ' ', $text);

        return array_values(array_filter(preg_split('/[\s,]+/', trim($text)) ?: [], fn (string $token) => $token !== ''));
    }

    private static function strip(string $text, string ...$words): string
    {
        $pattern = '/\b(?:'.implode('|', array_map(fn (string $word) => preg_quote($word, '/'), $words)).')\b/iu';

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace($pattern, ' ', $text)));
    }
}
