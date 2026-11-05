<?php

declare(strict_types=1);

namespace Unitary\Parsing;

use InvalidArgumentException;
use Unitary\Exceptions\ParseException;

/**
 * Strict, canonical Roman numerals (I to MMMCMXCIX). Not used by freeform
 * parsing, since letters like V, L, C and M are also unit symbols.
 *
 * @internal
 */
final class RomanNumerals
{
    private const VALUES = [
        'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90,
        'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1,
    ];

    private const CANONICAL = '/^M{0,3}(?:CM|CD|D?C{0,3})(?:XC|XL|L?X{0,3})(?:IX|IV|V?I{0,3})$/';

    public static function toInt(string $numeral): int
    {
        return self::tryToInt($numeral) ?? throw ParseException::cannotParse($numeral, 'Roman numeral');
    }

    public static function tryToInt(string $numeral): ?int
    {
        $numeral = trim($numeral);

        if ($numeral !== strtoupper($numeral) && $numeral !== strtolower($numeral)) {
            return null; // mixed case ("Xiv") is more likely a word than a numeral
        }

        $numeral = strtoupper($numeral);

        if ($numeral === '' || ! preg_match(self::CANONICAL, $numeral)) {
            return null;
        }

        $value = 0;
        $position = 0;

        while ($position < strlen($numeral)) {
            $pair = substr($numeral, $position, 2);

            if (strlen($pair) === 2 && isset(self::VALUES[$pair])) {
                $value += self::VALUES[$pair];
                $position += 2;
            } else {
                $value += self::VALUES[$numeral[$position]];
                $position++;
            }
        }

        return $value;
    }

    public static function fromInt(int $value): string
    {
        if ($value < 1 || $value > 3999) {
            throw new InvalidArgumentException("Roman numerals can only represent 1 to 3999, got {$value}");
        }

        $numeral = '';

        foreach (self::VALUES as $symbol => $amount) {
            while ($value >= $amount) {
                $numeral .= $symbol;
                $value -= $amount;
            }
        }

        return $numeral;
    }
}
