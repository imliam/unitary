<?php

declare(strict_types=1);

namespace Unitary\Parsing;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use Unitary\Currency\Currency;

/**
 * Rewrites English number words in freeform text into numerals that
 * NumberParser understands, leaving everything else untouched:
 *
 *     "two and a half miles"      => "2 1/2 miles"
 *     "a quarter of an hour"      => "1/4 hour"
 *     "one hundred and five km"   => "105 km"
 *     "1.2 million dollars"       => "1200000 dollars"
 *     "£3.5k"                     => "£3500"
 *
 * Deliberately conservative: vague words ("a few") are left alone, and the
 * "k"/"m" multipliers only apply next to a currency, since "5m" is 5 meters.
 *
 * @internal
 */
final class NumberWords
{
    private const UNITS = [
        'zero' => 0, 'nought' => 0, 'naught' => 0, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4,
        'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9,
    ];

    private const TEENS = [
        'ten' => 10, 'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15,
        'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18, 'nineteen' => 19,
    ];

    private const TENS = [
        'twenty' => 20, 'thirty' => 30, 'forty' => 40, 'fourty' => 40, 'fifty' => 50, 'sixty' => 60,
        'seventy' => 70, 'eighty' => 80, 'ninety' => 90,
    ];

    private const LARGE = [
        'thousand' => 3, 'million' => 6, 'billion' => 9, 'trillion' => 12,
    ];

    /** Collective nouns that can follow a cardinal ("two dozen") or an article ("a dozen"). */
    private const COLLECTIVES = ['dozen' => 12, 'dozens' => 12, 'score' => 20, 'gross' => 144];

    /** Collective nouns that only count after an article ("a couple of hours"). */
    private const ARTICLE_COLLECTIVES = ['couple' => 2, 'pair' => 2];

    private const DENOMINATORS = [
        'half' => 2, 'halves' => 2, 'third' => 3, 'thirds' => 3, 'quarter' => 4, 'quarters' => 4,
        'fourth' => 4, 'fourths' => 4, 'fifth' => 5, 'fifths' => 5, 'sixth' => 6, 'sixths' => 6,
        'seventh' => 7, 'sevenths' => 7, 'eighth' => 8, 'eighths' => 8, 'ninth' => 9, 'ninths' => 9,
        'tenth' => 10, 'tenths' => 10, 'twelfth' => 12, 'twelfths' => 12, 'sixteenth' => 16,
        'sixteenths' => 16, 'hundredth' => 100, 'hundredths' => 100, 'thousandth' => 1000,
        'thousandths' => 1000,
    ];

    /** Words after "a"/"an" that mean the article isn't a count. */
    private const VAGUE = ['few', 'lot', 'lots', 'bit', 'little', 'number', 'while', 'bunch', 'great', 'good', 'total', 'couple', 'pair', 'and'];

    private const CURRENCY_WORDS = ['dollar', 'dollars', 'pound', 'pounds', 'euro', 'euros', 'quid', 'buck', 'bucks', 'yen', 'rupee', 'rupees'];

    private const TOKEN = '/(?:\d+(?:,\d{3})*(?:\.\d+)?|\.\d+)|\p{L}+(?:[-\'’]\p{L}+)*|\s+|./su';

    public static function normalize(string $input): string
    {
        $text = self::expandMultipliers($input);

        if (! preg_match_all(self::TOKEN, $text, $matches)) {
            return $text;
        }

        $tokens = $matches[0];
        $count = count($tokens);
        $output = '';

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (self::isTerm($token) && self::startsTerm($tokens, $i)) {
                $items = self::items($tokens, $i);
                $phrase = self::phrase($items);

                if ($phrase !== null) {
                    $output .= self::format($phrase['value'], $phrase['decimal']);
                    $i = $items[$phrase['end'] - 1]['token'];

                    continue;
                }

                if (self::isLoneArticle($tokens, $i)) {
                    $output .= '1';

                    continue;
                }
            }

            $output .= $token;
        }

        return $output;
    }

    /**
     * Applies "bn"/"mn"/"million" style suffixes attached to numerals, and the
     * "k"/"m" shorthands when a currency makes the meaning unambiguous.
     */
    private static function expandMultipliers(string $text): string
    {
        $text = (string) preg_replace_callback(
            '/(?<![\p{L}\d.,])(\d+(?:\.\d+)?)(bn|mn|thousand|million|billion|trillion)(?![\p{L}\d])/iu',
            fn (array $m) => self::scaled($m[1], match (strtolower($m[2])) {
                'thousand' => 3, 'mn', 'million' => 6, 'bn', 'billion' => 9, default => 12,
            }),
            $text,
        );

        [$prefixes, $suffixes] = self::currencyPatterns();

        return (string) preg_replace_callback(
            '/(?<pre>(?:'.$prefixes.')\s?)?(?<![\p{L}\d.,])(?<num>\d+(?:\.\d+)?)(?<mul>[kKmM])(?![\p{L}\d])(?<post>\s?(?:'.$suffixes.')(?![\p{L}]))?/u',
            function (array $m): string {
                $pre = $m['pre'] ?? '';
                $post = $m['post'] ?? '';

                if ($pre === '' && $post === '') {
                    return $m[0];
                }

                return $pre.self::scaled($m['num'], strtolower($m['mul']) === 'k' ? 3 : 6).$post;
            },
            $text,
        );
    }

    private static function scaled(string $number, int $exponent): string
    {
        return (string) BigDecimal::of($number)->withPointMovedRight($exponent)->strippedOfTrailingZeros();
    }

    /**
     * Regex alternations for currency markers before and after an amount.
     *
     * @return array{string, string}
     */
    private static function currencyPatterns(): array
    {
        /** @var array{string, string}|null $patterns */
        static $patterns = null;

        if ($patterns !== null) {
            return $patterns;
        }

        $symbols = [];
        $codes = [];

        foreach (Currency::cases() as $currency) {
            $codes[] = $currency->value;

            foreach ($currency->symbols() as $symbol) {
                // Letter symbols ("R", "kr", "Q") are too easily confused with units.
                if (preg_match('/^\P{L}|\$$/u', $symbol)) {
                    $symbols[] = $symbol;
                }
            }
        }

        return $patterns = [
            self::alternation([...$symbols, ...$codes]),
            self::alternation([...$symbols, ...$codes]).'|(?i:'.self::alternation(self::CURRENCY_WORDS).')',
        ];
    }

    /**
     * @param  list<string>  $values
     */
    private static function alternation(array $values): string
    {
        $values = array_unique($values);
        usort($values, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return implode('|', array_map(fn (string $value) => preg_quote($value, '/'), $values));
    }

    private static function isTerm(string $token): bool
    {
        return (bool) preg_match('/^(?:[\p{L}\d]|\.\d)/u', $token);
    }

    /**
     * A phrase can't start glued to a preceding letter or digit ("a4", "x2").
     *
     * @param  list<string>  $tokens
     */
    private static function startsTerm(array $tokens, int $i): bool
    {
        $previous = $tokens[$i - 1] ?? null;

        return $previous === null || ! preg_match('/[\p{L}\d.,]$/u', $previous);
    }

    /**
     * "a mile" => "1 mile": only at the start of a term, and only before a plain word.
     *
     * @param  list<string>  $tokens
     */
    private static function isLoneArticle(array $tokens, int $i): bool
    {
        if (! in_array(strtolower($tokens[$i]), ['a', 'an'], true)) {
            return false;
        }

        $next = $tokens[$i + 2] ?? null;

        if (! isset($tokens[$i + 1]) || ! ctype_space($tokens[$i + 1]) || $next === null || ! preg_match('/^\p{L}/u', $next)) {
            return false;
        }

        $word = self::key($next);

        if (self::isNumberWord($word) || in_array($word, self::VAGUE, true)) {
            return false;
        }

        for ($j = $i - 1; $j >= 0; $j--) {
            if (ctype_space($tokens[$j])) {
                continue;
            }

            return in_array(strtolower($tokens[$j]), ['and', ',', '+', '&'], true);
        }

        return true;
    }

    /**
     * Splits the run of whitespace-separated words/numerals starting at $start
     * into items. Hyphenated number words ("twenty-three") become several items.
     *
     * @param  list<string>  $tokens
     * @return list<array{word: string, numeral: bool, token: int, last: bool}>
     */
    private static function items(array $tokens, int $start): array
    {
        $items = [];
        $count = count($tokens);

        for ($i = $start; $i < $count && count($items) < 24; $i += 2) {
            $token = $tokens[$i];

            if (preg_match('/^(?:\d|\.\d)/', $token)) {
                $items[] = ['word' => str_replace(',', '', $token), 'numeral' => true, 'token' => $i, 'last' => true];
            } elseif (preg_match('/^\p{L}/u', $token)) {
                $word = self::key($token);
                $parts = explode('-', $word);
                $isNumber = count($parts) > 1 && array_filter($parts, fn (string $part) => ! self::isNumberWord($part)) === [];

                foreach ($isNumber ? $parts : [$word] as $index => $part) {
                    $items[] = ['word' => $part, 'numeral' => false, 'token' => $i, 'last' => ! $isNumber || $index === count($parts) - 1];
                }
            } else {
                break;
            }

            // British writing may put a comma after a scale word: "one thousand, two hundred".
            // Only skip it when another number word follows, so "2 lb, 3 oz" stays two parts.
            if (($tokens[$i + 1] ?? '') === ',' && isset(self::LARGE[self::key($token)])
                && ctype_space($tokens[$i + 2] ?? '') && self::isNumberWord(self::key(explode('-', $tokens[$i + 3] ?? '')[0]))) {
                $i++;

                continue;
            }

            if (! isset($tokens[$i + 1]) || ! ctype_space($tokens[$i + 1])) {
                break;
            }
        }

        return $items;
    }

    private static function key(string $word): string
    {
        return str_replace('’', "'", mb_strtolower($word));
    }

    private static function isNumberWord(string $word): bool
    {
        return isset(self::UNITS[$word]) || isset(self::TEENS[$word]) || isset(self::TENS[$word])
            || isset(self::LARGE[$word]) || isset(self::DENOMINATORS[$word]) || isset(self::COLLECTIVES[$word])
            || in_array($word, ['hundred', 'minus', 'negative', 'point'], true);
    }

    /**
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     * @return array{value: BigRational, decimal: bool, end: int}|null
     */
    private static function phrase(array $items): ?array
    {
        $position = 0;
        $negative = in_array(self::word($items, 0), ['minus', 'negative'], true);

        if ($negative) {
            $position = 1;
        }

        $result = self::unsigned($items, $position);

        if ($result === null || ! ($items[$result['end'] - 1]['last'] ?? false)) {
            return null;
        }

        $hasWord = false;

        for ($i = 0; $i < $result['end']; $i++) {
            $hasWord = $hasWord || ! $items[$i]['numeral'];
        }

        if (! $hasWord) {
            return null;
        }

        return $negative ? ['value' => $result['value']->negated(), 'decimal' => $result['decimal'], 'end' => $result['end']] : $result;
    }

    /**
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     * @return array{value: BigRational, decimal: bool, end: int}|null
     */
    private static function unsigned(array $items, int $p): ?array
    {
        $word = self::word($items, $p);

        if ($word === 'a' || $word === 'an') {
            $next = self::word($items, $p + 1);

            if ($next === 'hundred' || isset(self::LARGE[$next])) {
                return self::cardinal($items, $p + 1, BigRational::one());
            }

            if ($next === "baker's" && self::word($items, $p + 2) === 'dozen') {
                return self::droppingOf($items, BigRational::of(13), $p + 3);
            }

            if (isset(self::COLLECTIVES[$next]) || isset(self::ARTICLE_COLLECTIVES[$next])) {
                return self::droppingOf($items, BigRational::of(self::COLLECTIVES[$next] ?? self::ARTICLE_COLLECTIVES[$next] ?? 1), $p + 2);
            }

            if (isset(self::DENOMINATORS[$next])) {
                return self::afterFraction($items, BigRational::ofFraction(1, self::DENOMINATORS[$next]), $p + 2);
            }

            return null;
        }

        if ($word === 'half' || $word === 'quarter') {
            return self::afterFraction($items, BigRational::ofFraction(1, self::DENOMINATORS[$word]), $p + 1);
        }

        $cardinal = self::cardinal($items, $p, null);

        if ($cardinal === null) {
            return null;
        }

        $q = $cardinal['end'];
        $next = self::word($items, $q);

        if (! $cardinal['decimal'] && $next === 'and' && ($fraction = self::fraction($items, $q + 1)) !== null) {
            return ['value' => $cardinal['value']->plus($fraction['value']), 'decimal' => false, 'end' => $fraction['end']];
        }

        if (isset(self::DENOMINATORS[$next])) {
            return self::afterFraction($items, $cardinal['value']->dividedBy(self::DENOMINATORS[$next]), $q + 1, $cardinal['decimal']);
        }

        if (isset(self::COLLECTIVES[$next])) {
            return self::droppingOf($items, $cardinal['value']->multipliedBy(self::COLLECTIVES[$next]), $q + 1, $cardinal['decimal']);
        }

        return $cardinal;
    }

    /**
     * A fraction after "and": "a half", "a quarter", "three quarters", "two thirds".
     *
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     * @return array{value: BigRational, end: int}|null
     */
    private static function fraction(array $items, int $p): ?array
    {
        $word = self::word($items, $p);

        if (($word === 'a' || $word === 'an') && isset(self::DENOMINATORS[self::word($items, $p + 1)])) {
            return ['value' => BigRational::ofFraction(1, self::DENOMINATORS[self::word($items, $p + 1)]), 'end' => $p + 2];
        }

        $numerator = self::cardinal($items, $p, null);

        if ($numerator === null || $numerator['decimal'] || ! isset(self::DENOMINATORS[self::word($items, $numerator['end'])])) {
            return null;
        }

        return [
            'value' => $numerator['value']->dividedBy(self::DENOMINATORS[self::word($items, $numerator['end'])]),
            'end' => $numerator['end'] + 1,
        ];
    }

    /**
     * After a fraction: "half a dozen" multiplies, while "of a"/"of an"/"a"
     * before a plain word is dropped ("half a cup", "a quarter of an hour").
     *
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     * @return array{value: BigRational, decimal: bool, end: int}
     */
    private static function afterFraction(array $items, BigRational $value, int $q, bool $decimal = false): array
    {
        $next = self::word($items, $q);
        $article = self::word($items, $q + 1);

        if (($next === 'a' || $next === 'an') && isset(self::COLLECTIVES[$article])) {
            return self::droppingOf($items, $value->multipliedBy(self::COLLECTIVES[$article]), $q + 2, $decimal);
        }

        if ($next === 'of' && ($article === 'a' || $article === 'an') && self::isPlainWord($items, $q + 2)) {
            return ['value' => $value, 'decimal' => $decimal, 'end' => $q + 2];
        }

        if (($next === 'a' || $next === 'an') && self::isPlainWord($items, $q + 1)) {
            return ['value' => $value, 'decimal' => $decimal, 'end' => $q + 1];
        }

        return ['value' => $value, 'decimal' => $decimal, 'end' => $q];
    }

    /**
     * "a couple of hours" => "2 hours".
     *
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     * @return array{value: BigRational, decimal: bool, end: int}
     */
    private static function droppingOf(array $items, BigRational $value, int $q, bool $decimal = false): array
    {
        return ['value' => $value, 'decimal' => $decimal, 'end' => self::word($items, $q) === 'of' ? $q + 1 : $q];
    }

    /**
     * Reads a cardinal number: words ("one hundred and five", "twenty-three",
     * "zero point five") or a numeral followed by scale words ("2 million").
     *
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     * @return array{value: BigRational, decimal: bool, end: int}|null
     */
    private static function cardinal(array $items, int $p, ?BigRational $seed): ?array
    {
        if ($seed === null && ($items[$p]['numeral'] ?? false)) {
            $numeral = $items[$p]['word'];
            $value = BigRational::of($numeral);
            $q = $p + 1;

            while (self::word($items, $q) === 'hundred' || isset(self::LARGE[self::word($items, $q)])) {
                $value = $value->multipliedBy(BigRational::ten()->power(self::LARGE[self::word($items, $q)] ?? 2));
                $q++;
            }

            return ['value' => $value, 'decimal' => str_contains($numeral, '.'), 'end' => $q];
        }

        $total = BigRational::zero();
        $current = $seed ?? BigRational::zero();
        $last = $seed === null ? 'none' : 'unit';
        $q = $p;

        while (($word = self::word($items, $q)) !== '') {
            if (isset(self::UNITS[$word])) {
                if (in_array($last, ['unit', 'teen', 'zero'], true) || (self::UNITS[$word] === 0 && $last !== 'none')) {
                    break;
                }

                $current = $current->plus(self::UNITS[$word]);
                $last = self::UNITS[$word] === 0 ? 'zero' : 'unit';
            } elseif (isset(self::TEENS[$word]) || isset(self::TENS[$word])) {
                if (! in_array($last, ['none', 'hundred', 'large', 'and'], true)) {
                    break;
                }

                $current = $current->plus(self::TEENS[$word] ?? self::TENS[$word] ?? 0);
                $last = isset(self::TEENS[$word]) ? 'teen' : 'tens';
            } elseif ($word === 'hundred') {
                if (! in_array($last, ['unit', 'teen', 'tens'], true)) {
                    break;
                }

                $current = $current->multipliedBy(100);
                $last = 'hundred';
            } elseif (isset(self::LARGE[$word])) {
                if (! in_array($last, ['unit', 'teen', 'tens', 'hundred'], true)) {
                    break;
                }

                $total = $total->plus($current->multipliedBy(BigRational::ten()->power(self::LARGE[$word])));
                $current = BigRational::zero();
                $last = 'large';
            } elseif ($word === 'and' && in_array($last, ['hundred', 'large'], true)) {
                $next = self::word($items, $q + 1);

                if (! isset(self::UNITS[$next]) && ! isset(self::TEENS[$next]) && ! isset(self::TENS[$next])) {
                    break;
                }

                $last = 'and';
            } elseif ($word === 'point' && in_array($last, ['zero', 'unit', 'teen', 'tens'], true)) {
                return self::decimal($items, $q, $total->plus($current)) ?? ['value' => $total->plus($current), 'decimal' => false, 'end' => $q];
            } else {
                break;
            }

            $q++;
        }

        return $q === $p ? null : ['value' => $total->plus($current), 'decimal' => false, 'end' => $q];
    }

    /**
     * "point five", "point one four" (optionally followed by "million" etc.).
     *
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     * @return array{value: BigRational, decimal: bool, end: int}|null
     */
    private static function decimal(array $items, int $q, BigRational $whole): ?array
    {
        $digits = '';
        $r = $q + 1;

        while (isset(self::UNITS[self::word($items, $r)])) {
            $digits .= self::UNITS[self::word($items, $r)];
            $r++;
        }

        if ($digits === '') {
            return null;
        }

        $value = BigRational::of($whole->getIntegralPart().'.'.$digits);

        while (isset(self::LARGE[self::word($items, $r)])) {
            $value = $value->multipliedBy(BigRational::ten()->power(self::LARGE[self::word($items, $r)]));
            $r++;
        }

        return ['value' => $value, 'decimal' => true, 'end' => $r];
    }

    /**
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     */
    private static function word(array $items, int $index): string
    {
        $item = $items[$index] ?? null;

        return $item === null || $item['numeral'] ? '' : $item['word'];
    }

    /**
     * @param  list<array{word: string, numeral: bool, token: int, last: bool}>  $items
     */
    private static function isPlainWord(array $items, int $index): bool
    {
        $word = self::word($items, $index);

        return $word !== '' && ! self::isNumberWord($word);
    }

    private static function format(BigRational $value, bool $decimal): string
    {
        if ($value->getDenominator()->isEqualTo(1)) {
            return (string) $value->getNumerator();
        }

        if ($decimal) {
            try {
                return (string) $value->toBigDecimal();
            } catch (RoundingNecessaryException) {
            }
        }

        $absolute = $value->abs();
        $whole = $absolute->getIntegralPart();
        $fraction = $absolute->getFractionalPart();
        $sign = $value->isNegative() ? '-' : '';
        $fractionText = $fraction->getNumerator().'/'.$fraction->getDenominator();

        return $sign.($whole->isZero() ? $fractionText : $whole.' '.$fractionText);
    }
}
