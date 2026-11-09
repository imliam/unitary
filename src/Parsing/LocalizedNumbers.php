<?php

declare(strict_types=1);

namespace Unitary\Parsing;

use IntlChar;
use NumberFormatter;

/**
 * Rewrites numbers written the way a locale writes them into the form the
 * rest of the parser reads: "1.234,5" (German) → "1234.5", "1 234,5" (French)
 * → "1234.5", "1’234.5" (Swiss German) → "1234.5", "١٢٫٥" (Arabic) → "12.5".
 *
 * A number only counts as the locale's when it fits the locale's pattern, with
 * grouping in threes. Anything else is left alone for the English rules, so
 * "1.5" still means one and a half in German.
 *
 * Separators come from ICU (ext-intl). Without it, a table of common locales
 * is used, and other locales are read the English way.
 *
 * @internal
 */
final class LocalizedNumbers
{
    /**
     * Decimal and grouping separators by language, for when ext-intl isn't installed.
     */
    private const SEPARATORS = [
        'de' => [',', '.'], 'es' => [',', '.'], 'it' => [',', '.'], 'nl' => [',', '.'], 'pt' => [',', '.'],
        'da' => [',', '.'], 'id' => [',', '.'], 'tr' => [',', '.'], 'el' => [',', '.'], 'ro' => [',', '.'],
        'fr' => [',', ' '], 'ru' => [',', ' '], 'pl' => [',', ' '], 'sv' => [',', ' '], 'nb' => [',', ' '],
        'fi' => [',', ' '], 'cs' => [',', ' '], 'uk' => [',', ' '], 'sk' => [',', ' '], 'hu' => [',', ' '],
    ];

    /**
     * Characters treated as a space in numbers grouped with spaces, like "1 234".
     */
    private const SPACES = " \u{00A0}\u{202F}\u{2009}";

    /**
     * The zero of each common non-Latin digit set, for when ext-intl isn't installed.
     */
    private const ZEROS = [0x0660, 0x06F0, 0x07C0, 0x0966, 0x09E6, 0x0A66, 0x0AE6, 0x0B66, 0x0BE6, 0x0C66, 0x0CE6, 0x0D66, 0x0E50, 0x0ED0, 0x0F20, 0x1040, 0x17E0, 0x1810, 0xFF10];

    public static function normalize(string $text, string $locale): string
    {
        $text = self::latinDigits($text);

        // Arabic's own decimal and thousands separators go with its digits, whatever the locale's
        // default numbering is: "١٢٫٥" is 12.5 and "١٬٢٣٤" is 1,234.
        $text = (string) preg_replace(['/(?<=\d)\x{066B}(?=\d)/u', '/(?<=\d)\x{066C}(?=\d)/u'], ['.', ','], $text);
        [$decimal, $grouping, $secondaryGrouping] = self::separators($locale);

        if ($decimal === '.' && $grouping === ',' && $secondaryGrouping === 3) {
            return $text;
        }

        $g = self::isSpace($grouping) ? '['.self::SPACES.']' : preg_quote($grouping, '/');
        $d = preg_quote($decimal, '/');

        // Grouped in threes, like "1.234.567", or, in India, in twos after the first three, like "12,34,567".
        $grouped = $secondaryGrouping === 2 ? "\\d{1,2}(?:{$g}\\d{2})*{$g}\\d{3}" : "\\d{1,3}(?:{$g}\\d{3})+";

        $replacements = [$decimal => '.'];

        foreach (self::isSpace($grouping) ? mb_str_split(self::SPACES) : [$grouping] as $separator) {
            $replacements[$separator] = '';
        }

        return (string) preg_replace_callback(
            "/(?<![\\d.,])(?:{$grouped}(?:{$d}\\d+)?|\\d+{$d}\\d+)(?![\\d])/u",
            fn (array $matches): string => strtr($matches[0], $replacements),
            $text,
        );
    }

    /**
     * The locale's decimal separator, grouping separator and the size of groups after the
     * first: [",", ".", 3] for German, [".", ",", 2] for Indian English.
     *
     * @return array{string, string, int}
     */
    public static function separators(string $locale): array
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(str_replace('-', '_', $locale), NumberFormatter::DECIMAL);
            $decimal = (string) $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
            $grouping = (string) $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
            $secondary = (int) $formatter->getAttribute(NumberFormatter::SECONDARY_GROUPING_SIZE);

            if ($decimal !== '') {
                return [$decimal, $grouping, $secondary > 0 ? $secondary : 3];
            }
        }

        $language = strtolower(explode('_', str_replace('-', '_', $locale))[0]);

        return [...(self::SEPARATORS[$language] ?? ['.', ',']), 3];
    }

    /**
     * Rewrites digits from other scripts as 0–9: "١٢٣" → "123".
     */
    private static function latinDigits(string $text): string
    {
        return (string) preg_replace_callback('/[^\x00-\x7F]/u', function (array $matches): string {
            $codePoint = mb_ord($matches[0]);

            if (class_exists(IntlChar::class)) {
                $digit = IntlChar::charDigitValue($codePoint);

                return $digit >= 0 && IntlChar::isdigit($codePoint) ? (string) $digit : $matches[0];
            }

            foreach (self::ZEROS as $zero) {
                if ($codePoint >= $zero && $codePoint <= $zero + 9) {
                    return (string) ($codePoint - $zero);
                }
            }

            return $matches[0];
        }, $text);
    }

    /**
     * Whether the separator is a space of any kind. trim() works on bytes, so it can't tell.
     */
    private static function isSpace(string $separator): bool
    {
        return preg_match('/^[\s\x{00A0}\x{202F}\x{2009}]+$/u', $separator) === 1;
    }
}
