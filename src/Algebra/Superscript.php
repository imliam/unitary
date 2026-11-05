<?php

declare(strict_types=1);

namespace Unitary\Algebra;

/**
 * @internal
 */
final class Superscript
{
    private const DIGITS = ['-' => '⁻', '0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹'];

    public static function of(int $exponent): string
    {
        return strtr((string) $exponent, self::DIGITS);
    }

    /** Parses "²", "⁻¹" etc. back into an integer, or null if the text isn't a superscript number. */
    public static function parse(string $text): ?int
    {
        $plain = strtr($text, array_flip(self::DIGITS));

        return preg_match('/^-?\d+$/', $plain) && $plain !== $text ? (int) $plain : null;
    }

    public static function pattern(): string
    {
        return '[⁻⁰¹²³⁴⁵⁶⁷⁸⁹]+';
    }
}
