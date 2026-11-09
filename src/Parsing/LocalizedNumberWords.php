<?php

declare(strict_types=1);

namespace Unitary\Parsing;

use NumberFormatter;

/**
 * Rewrites numbers written as words in other languages as numerals: "zwei
 * Meter" → "2 Meter", "quatre-vingt-dix-neuf" → "99", "二千一" → "2001".
 *
 * Plain number words are read with ICU's spell-out rules (ext-intl), which
 * know every CLDR locale. Idioms ICU doesn't read, like German "dreieinhalb",
 * French "deux heures et demie" or Spanish "una docena", come from a table of
 * common languages. English is left to NumberWords, which knows more idioms.
 *
 * @internal
 */
final class LocalizedNumberWords
{
    /**
     * Idioms by language. "{n}" stands for a number, written as a numeral or a word, and
     * "{unit}" for a unit name. Each rewrite is a value expression over {n}: "{n}+1/2" is
     * a half more than {n}, "{n}*12" is {n} dozen. Rewrites with "{unit}" keep the unit,
     * and a trailing space keeps a word joined by a hyphen apart: "demi-litre" → "1/2 litre".
     * The "de" or "di" that joins a quantity word to its unit is dropped: "une douzaine de".
     *
     * @var array<string, list<array{string, string}>>
     */
    private const IDIOMS = [
        'de' => [
            ['/\banderthalb\b/iu', '3/2'],
            ['/\b{n}(?:und)?einhalb\b/iu', '{n}+1/2'],
            ['/\b{n}viertel\b/iu', '{n}/4'],
            ['/\b(?:ein|eine|einen|einem)\s+viertel\b/iu', '1/4'],
            ['/\b(?:ein|eine|einen|einem)\s+halbe[nmrs]?\b/iu', '1/2'],
            ['/\b(?:ein|eine|einem)\s+halbes\s+dutzend\b/iu', '6'],
            ['/\b(?:ein|einem)\s+dutzend\b/iu', '12'],
            ['/\b{n}\s+dutzend\b/iu', '{n}*12'],
        ],
        'fr' => [
            ['/\b{n}\s+{unit}\s+et\s+demie?\b/iu', '{n}+1/2 {unit}'],
            ['/\b{n}\s+{unit}\s+et\s+quart\b/iu', '{n}+1/4 {unit}'],
            ['/\b{n}\s+et\s+demie?\b/iu', '{n}+1/2'],
            ['/\b(?:une\s+)?demi-douzaine(?:\s+de\b|\s+d[\'’])?/iu', '6'],
            ['/\b(?:une\s+)?douzaine(?:\s+de\b|\s+d[\'’])?/iu', '12'],
            ['/\b{n}\s+douzaines(?:\s+de\b|\s+d[\'’])?/iu', '{n}*12'],
            ['/\b(?:un\s+|une\s+)?demi-(?=\p{L})/iu', '1/2 '],
            ['/\bun\s+quart(?:\s+de\b|\s+d[\'’])?/iu', '1/4'],
            ['/\b{n}\s+quarts(?:\s+de\b|\s+d[\'’])?/iu', '{n}/4'],
        ],
        'es' => [
            ['/\b{n}\s+{unit}\s+y\s+medi[oa]\b/iu', '{n}+1/2 {unit}'],
            ['/\b{n}\s+{unit}\s+y\s+cuarto\b/iu', '{n}+1/4 {unit}'],
            ['/\b{n}\s+y\s+medi[oa]\b/iu', '{n}+1/2'],
            ['/\bmedia\s+docena(?:\s+de\b)?/iu', '6'],
            ['/\b(?:una\s+)?docena(?:\s+de\b)?/iu', '12'],
            ['/\b{n}\s+docenas(?:\s+de\b)?/iu', '{n}*12'],
            ['/\b(?:un\s+|una\s+)?medi[oa]\b(?=\s+\p{L})/iu', '1/2'],
            ['/\bun\s+cuarto\b/iu', '1/4'],
            ['/\b{n}\s+cuartos\b/iu', '{n}/4'],
        ],
        'it' => [
            ['/\b{n}\s+{unit}\s+e\s+mezz[oa]\b/iu', '{n}+1/2 {unit}'],
            ['/\b{n}\s+e\s+mezz[oa]\b/iu', '{n}+1/2'],
            ['/\bmezza\s+dozzina(?:\s+di\b)?/iu', '6'],
            ['/\b(?:una\s+)?dozzina(?:\s+di\b)?/iu', '12'],
            ['/\b{n}\s+dozzine(?:\s+di\b)?/iu', '{n}*12'],
            ['/\b(?:un\s+|una\s+|un\')?mezz[oa]\b(?=\s+\p{L})/iu', '1/2'],
            ['/\bun\s+quarto\b/iu', '1/4'],
        ],
        'pt' => [
            ['/\b{n}\s+{unit}\s+e\s+mei[oa]\b/iu', '{n}+1/2 {unit}'],
            ['/\b{n}\s+e\s+mei[oa]\b/iu', '{n}+1/2'],
            ['/\bmeia\s+dúzia(?:\s+de\b)?/iu', '6'],
            ['/\b(?:uma\s+)?dúzia(?:\s+de\b)?/iu', '12'],
            ['/\b{n}\s+dúzias(?:\s+de\b)?/iu', '{n}*12'],
            ['/\b(?:um\s+|uma\s+)?mei[oa]\b(?=\s+\p{L})/iu', '1/2'],
            ['/\bum\s+quarto\b/iu', '1/4'],
        ],
        'nl' => [
            ['/\banderhalf\b/iu', '3/2'],
            ['/\b{n}[eë]nhalf\b/iu', '{n}+1/2'],
            ['/\bdriekwart\b/iu', '3/4'],
            ['/\been\s+kwart\b/iu', '1/4'],
            ['/\been\s+halve?\b/iu', '1/2'],
            ['/\been\s+half\s+dozijn\b/iu', '6'],
            ['/\been\s+dozijn\b/iu', '12'],
            ['/\b{n}\s+dozijn\b/iu', '{n}*12'],
        ],
    ];

    /** A number in an idiom: a numeral, or one or more letters for ICU to read as a number word. */
    private const NUMBER = '(?<n>\d+(?:\.\d+)?|\p{L}+?)';

    /** A unit name in an idiom, like "heures" or "km". */
    private const UNIT = '(?<unit>\p{L}[\p{L}.]*)';

    /** Scripts written without spaces between words, where a number word can touch a unit. */
    private const UNSPACED = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}]/u';

    /** @var array<string, NumberFormatter|false> */
    private static array $formatters = [];

    public static function normalize(string $text, string $locale): string
    {
        $language = strtolower(explode('_', str_replace('-', '_', $locale))[0]);

        if ($language === 'en') {
            return $text;
        }

        return self::spellOut(self::idioms($text, $language, $locale), $locale);
    }

    /**
     * Rewrites the language's idioms, like "dreieinhalb" or "deux heures et demie".
     */
    private static function idioms(string $text, string $language, string $locale): string
    {
        foreach (self::IDIOMS[$language] ?? [] as [$pattern, $rewrite]) {
            $pattern = str_replace(['{n}', '{unit}'], [self::NUMBER, self::UNIT], $pattern);

            $text = (string) preg_replace_callback($pattern, function (array $matches) use ($rewrite, $locale): string {
                $number = isset($matches['n']) ? self::value($matches['n'], $locale) : null;

                if (isset($matches['n']) && $number === null) {
                    return $matches[0];
                }

                $parts = explode(' ', $rewrite, 2);
                $expression = $parts[0];
                $rest = $parts[1] ?? null;

                // An elided "d’" runs straight into the unit, so put back the space it took.
                $elided = preg_match('/[\'’]$/u', $matches[0]) === 1 ? ' ' : '';

                return self::evaluate(str_replace('{n}', (string) $number, $expression))
                    .($rest === null ? $elided : ' '.str_replace('{unit}', $matches['unit'] ?? '', $rest));
            }, $text);
        }

        return $text;
    }

    /**
     * Rewrites number words ICU can read in the locale, like "zweitausendeins" or "deux mille".
     */
    private static function spellOut(string $text, string $locale): string
    {
        $formatter = self::formatter($locale);

        if ($formatter === null) {
            return $text;
        }

        $characters = mb_str_split($text);
        $count = count($characters);
        $output = '';

        for ($i = 0; $i < $count; $i++) {
            if (self::startsNumberWord($characters, $i)) {
                $rest = implode('', array_slice($characters, $i));
                $position = 0;
                $value = $formatter->parse($rest, NumberFormatter::TYPE_DOUBLE, $position);
                $consumed = rtrim(mb_substr($rest, 0, $position));
                $length = mb_strlen($consumed);
                $next = $characters[$i + $length] ?? '';

                if ($value !== false && $length > 0 && preg_match('/\p{L}/u', $consumed) === 1 && self::endsWord($consumed, $next)) {
                    $output .= self::format($value);
                    $i += $length - 1;

                    continue;
                }
            }

            $output .= $characters[$i];
        }

        return $output;
    }

    /**
     * A number word starts a word, and doesn't follow a numeral, so "5 milles" stays five
     * miles, not "5 1000", in French, where "mille" is a thousand and a mile.
     *
     * @param  list<string>  $characters
     */
    private static function startsNumberWord(array $characters, int $index): bool
    {
        if (preg_match('/\p{L}/u', $characters[$index]) !== 1) {
            return false;
        }

        $previous = $characters[$index - 1] ?? '';

        if (preg_match('/[\p{L}\p{Nd}]/u', $previous) === 1 && preg_match(self::UNSPACED, $previous.$characters[$index]) !== 1) {
            return false;
        }

        for ($j = $index - 1; $j >= 0 && trim($characters[$j]) === ''; $j--) {
        }

        return $j < 0 || preg_match('/[\d\/½⅓⅔¼¾]/u', $characters[$j]) !== 1;
    }

    /**
     * A number word has to end where a word ends, so "drei" isn't read from "dreieinhalb".
     * In scripts written without spaces, it may run straight into a unit: "五英尺".
     */
    private static function endsWord(string $consumed, string $next): bool
    {
        if ($next === '' || preg_match('/\p{L}/u', $next) !== 1) {
            return true;
        }

        return preg_match(self::UNSPACED, mb_substr($consumed, -1).$next) === 1;
    }

    /**
     * The value of a numeral or a number word, like "3" or "drei".
     */
    private static function value(string $number, string $locale): ?string
    {
        if (is_numeric($number)) {
            return $number;
        }

        $formatter = self::formatter($locale);

        if ($formatter === null) {
            return null;
        }

        $position = 0;
        $value = $formatter->parse($number, NumberFormatter::TYPE_DOUBLE, $position);

        return $value !== false && $position === mb_strlen($number) ? self::format($value) : null;
    }

    /**
     * Evaluates a rewrite like "3+1/2", "2*12" or "3/4" as a numeral or mixed number.
     */
    private static function evaluate(string $expression): string
    {
        if (preg_match('/^(?<n>[\d.]+)\+(?<a>\d+)\/(?<b>\d+)$/', $expression, $matches) === 1) {
            return is_numeric($matches['n']) && (float) $matches['n'] === floor((float) $matches['n'])
                ? $matches['n'].' '.$matches['a'].'/'.$matches['b']
                : self::format((float) $matches['n'] + (int) $matches['a'] / (int) $matches['b']);
        }

        if (preg_match('/^(?<n>[\d.]+)\*(?<m>\d+)$/', $expression, $matches) === 1) {
            return self::format((float) $matches['n'] * (int) $matches['m']);
        }

        return $expression;
    }

    private static function format(float|int $value): string
    {
        return floor((float) $value) === (float) $value
            ? (string) (int) $value
            : rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.');
    }

    private static function formatter(string $locale): ?NumberFormatter
    {
        if (! class_exists(NumberFormatter::class)) {
            return null;
        }

        $locale = str_replace('-', '_', $locale);

        if (! isset(self::$formatters[$locale])) {
            $formatter = new NumberFormatter($locale, NumberFormatter::SPELLOUT);
            $formatter->setAttribute(NumberFormatter::LENIENT_PARSE, 1);
            self::$formatters[$locale] = $formatter;
        }

        return self::$formatters[$locale] ?: null;
    }
}
