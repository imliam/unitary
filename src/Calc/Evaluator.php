<?php

declare(strict_types=1);

namespace Unitary\Calc;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Unitary\CompoundUnit;
use Unitary\Converter;
use Unitary\Currency\Currency;
use Unitary\Dimension;
use Unitary\Exceptions\ParseException;
use Unitary\Parsing\NumberParser;
use Unitary\Parsing\NumberWords;
use Unitary\Parsing\QuantityParser;
use Unitary\Quantity;
use Unitary\Substances\Substance;
use Unitary\Substances\SubstanceConverter;
use Unitary\Substances\Substances;
use Unitary\Support\Exact;
use Unitary\Support\Transcendental;
use Unitary\Unit;
use Unitary\Units\Ratio;

/**
 * Tokenizes and evaluates one arithmetic expression over quantities.
 *
 * Precedence, loosest first: + − · × ÷ at · of on off · unary − · ^
 *
 * @internal
 */
final class Evaluator
{
    private const SEPARATOR = '/\s(?:to|in|into|as)\s+|->|→/iu';

    /** Words that are operators or labels rather than parts of a unit. */
    private const KEYWORDS = ['of', 'on', 'off', 'at', 'each', 'plus', 'minus', 'times', 'x', 'divided', 'by', 'and'];

    /** @var array<string, array{int, bool}> operator => [precedence, right associative] */
    private const BINARY = [
        '+' => [10, false], 'plus' => [10, false], 'and' => [10, false],
        '-' => [10, false], 'minus' => [10, false],
        '*' => [20, false], '×' => [20, false], '·' => [20, false], '⋅' => [20, false], 'times' => [20, false], 'x' => [20, false], 'at' => [20, false], '@' => [20, false],
        '/' => [20, false], '÷' => [20, false], 'divided' => [20, false],
        'of' => [25, false], 'on' => [25, false], 'off' => [25, false],
        '^' => [30, true],
    ];

    private const UNARY_PRECEDENCE = 28;

    /** @var list<Token> */
    private array $tokens = [];

    private int $position = 0;

    private Context $context;

    /** Filler words that are fine to ignore even in strict mode. */
    private const FILLERS = ['each', 'by'];

    /**
     * @param  bool  $strict  Reject unknown words instead of treating them as labels.
     */
    public function __construct(private readonly Converter $converter, private readonly bool $strict = false)
    {
        $this->context = new Context();
    }

    /**
     * Evaluates an expression with an optional "to <unit>" / "as %" target.
     *
     * @return Quantity<Unit>
     */
    public function evaluateWithTarget(string $line, Context $context): Quantity
    {
        if (preg_match_all(self::SEPARATOR, $line, $separators, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach (array_reverse($separators) as [[$separator, $offset]]) {
                $source = trim(substr($line, 0, $offset));
                $target = trim(substr($line, $offset + strlen($separator)));

                if ($source === '' || $target === '') {
                    continue;
                }

                try {
                    $value = $this->evaluate($source, $context);
                } catch (ParseException) {
                    continue;
                }

                if (preg_match('/^(?:a\s+)?(?:%|percent(?:age)?)$/iu', $target)) {
                    return $value->to(Ratio::Percent);
                }

                if (str_starts_with($target, '/') && $value->unit() instanceof CompoundUnit) {
                    $target = $value->unit()->numeratorSymbol().$target;
                }

                $unit = $this->converter->units()->tryResolve($target, $value->dimension());

                if ($unit !== null) {
                    return $value->to($unit);
                }
            }
        }

        return $this->evaluate($line, $context);
    }

    /**
     * @return Quantity<Unit>
     */
    public function evaluate(string $expression, Context $context): Quantity
    {
        $this->context = $context;
        $this->tokens = $this->withoutLabels($this->tokenize(QuantityParser::normalize(NumberWords::normalize($expression))));
        $this->position = 0;

        if ($this->tokens === []) {
            throw ParseException::cannotParse($expression, 'expression');
        }

        $result = $this->parseExpression(0, $expression);
        $leftover = $this->peek();

        if ($leftover !== null) {
            throw ParseException::cannotParse($expression, 'expression', sprintf('unexpected "%s"', $leftover->text));
        }

        return $result;
    }

    /**
     * @return list<Token>
     */
    private function tokenize(string $text): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            if (preg_match('/\G\s+/u', $text, $m, 0, $offset)) {
                $offset += strlen($m[0]);

                continue;
            }

            // "$12", "£ 5", "US$3": a currency symbol written before a number.
            if (preg_match('/\G(?<symbol>[A-Z]{0,3}[^\s\w+\-−*×÷·⋅\/^()=,%@.]{1,3})\s?(?='.NumberParser::PATTERN.')/u', $text, $m, 0, $offset)
                && $this->isCurrency($m['symbol'])) {
                $offset += strlen($m[0]);
                [$number, $offset] = $this->number($text, $offset);
                [$unit, $offset] = $this->unit($text, $offset);

                $tokens[] = new Token(Token::NUMBER, $m[0], $number[0], $unit !== null && preg_match('/^(?:\/|per\s)/iu', $unit) ? $m['symbol'].($unit[0] === '/' ? '' : ' ').$unit : $m['symbol']);

                continue;
            }

            if (preg_match('/\G'.NumberParser::PATTERN.'/u', $text, $m, 0, $offset)) {
                [$number, $offset] = $this->number($text, $offset);
                [$unit, $offset] = $this->unit($text, $offset);
                [$substance, $offset] = $unit === null ? [null, $offset] : $this->substance($text, $offset);
                $tokens[] = new Token(Token::NUMBER, $m[0], $number[0], $unit, $number[1], $substance);

                continue;
            }

            if (preg_match('/\G(?:[+\-−*×÷·⋅\/^(),@]|=)/u', $text, $m, 0, $offset)) {
                $tokens[] = new Token(Token::OPERATOR, $m[0] === '−' ? '-' : $m[0]);
                $offset += strlen($m[0]);

                continue;
            }

            if (preg_match('/\G[\p{L}_°][\p{L}\p{N}_]*/u', $text, $m, 0, $offset)) {
                $word = $m[0];
                $offset += strlen($word);

                // "USD 5" or "GBP 23": a currency code before a number.
                if (Currency::tryFrom(strtoupper($word)) !== null && preg_match('/\G\s*'.NumberParser::PATTERN.'/u', $text, $n, 0, $offset)) {
                    $offset += strlen($n[0]) - strlen(ltrim($n[0]));
                    [$number, $offset] = $this->number($text, $offset);
                    $tokens[] = new Token(Token::NUMBER, $word, $number[0], strtoupper($word));

                    continue;
                }

                $tokens[] = new Token(Token::WORD, $word);

                continue;
            }

            throw ParseException::cannotParse($text, 'expression', sprintf('unexpected "%s"', mb_substr(substr($text, $offset), 0, 1)));
        }

        return $tokens;
    }

    /**
     * @return array{array{BigRational, ?int}, int}
     */
    private function number(string $text, int $offset): array
    {
        if (! preg_match('/\G'.NumberParser::PATTERN.'/u', $text, $m, 0, $offset)) {
            throw ParseException::cannotParse($text, 'number');
        }

        $written = ($m['dec'] ?? '') !== '' && ($m['vulgar'] ?? '') === '' ? $m['dec'].(($m['exp'] ?? '') !== '' ? 'e'.$m['exp'] : '') : null;

        return [[NumberParser::fromMatches(['sign' => ''] + $m), $written === null ? null : NumberParser::significantFigures($written)], $offset + strlen($m[0])];
    }

    /**
     * Reads the longest run of words after a number that names a unit.
     *
     * @return array{?string, int}
     */
    private function unit(string $text, int $offset): array
    {
        preg_match('/\G(?:[^\d+\-−*×÷()=,@\/^]|(?<=\p{L})-(?=\p{L})|\/(?=[\p{L}$£€¥])|(?<=[\p{L}²³])\^-?\d+|(?<=\p{L})[23](?![\d.,\/])|(?<=\/)\d+(?=\p{L}))*/u', $text, $m, 0, $offset);
        $raw = $m[0] ?? '';

        preg_match_all('/\S+/u', $raw, $words, PREG_OFFSET_CAPTURE);
        $words = $words[0];

        foreach ($words as $index => [$word]) {
            if (in_array(mb_strtolower($word), self::KEYWORDS, true)) {
                $words = array_slice($words, 0, $index);

                break;
            }
        }

        for ($count = count($words); $count > 0; $count--) {
            $last = $words[$count - 1];
            $candidate = substr($raw, 0, $last[1] + strlen($last[0]));

            if ($this->converter->units()->candidates(trim($candidate)) !== []) {
                return [trim($candidate), $offset + strlen($candidate)];
            }
        }

        return [null, $offset];
    }

    /**
     * Reads an ingredient or fuel after an amount: "1/2 cup of flour", "2 tbsp butter".
     *
     * @return array{?Substance, int}
     */
    private function substance(string $text, int $offset): array
    {
        if (! preg_match('/\G\s+(?:of\s+)?((?:[\p{L}\'’-]+\s*){1,4})/u', $text, $m, 0, $offset)) {
            return [null, $offset];
        }

        preg_match_all('/[\p{L}\'’-]+/u', $m[1], $words, PREG_OFFSET_CAPTURE);

        for ($count = count($words[0]); $count > 0; $count--) {
            $last = $words[0][$count - 1];
            $name = substr($m[1], 0, $last[1] + strlen($last[0]));

            if (in_array(mb_strtolower($words[0][0][0]), self::KEYWORDS, true)) {
                break;
            }

            if (($substance = Substances::find($name)) !== null) {
                return [$substance, $offset + strlen($m[0]) - strlen($m[1]) + strlen($name)];
            }
        }

        return [null, $offset];
    }

    /**
     * Drops words that aren't operators, variables, functions or constants ("3 pizzas at $12 each").
     *
     * @param  list<Token>  $tokens
     * @return list<Token>
     */
    private function withoutLabels(array $tokens): array
    {
        $kept = [];

        foreach ($tokens as $index => $token) {
            if (! $token->is(Token::WORD)) {
                $kept[] = $token;

                continue;
            }

            $word = mb_strtolower($token->text);
            $isFunction = ($tokens[$index + 1] ?? null)?->is(Token::OPERATOR, '(') === true && in_array($word, ['sqrt', 'round', 'floor', 'ceil', 'abs', 'min', 'max'], true);
            $isKnown = isset(self::BINARY[$word]) || isset($this->context->variables[$token->text])
                || in_array($word, ['prev', 'ans', 'above', 'sum', 'total', 'average', 'avg', 'pi', 'π'], true);

            if ($isFunction || $isKnown) {
                $kept[] = $token;
            } elseif ($this->strict && ! in_array($word, self::FILLERS, true)) {
                throw ParseException::cannotParse($token->text, 'expression', 'unknown word');
            }
        }

        return $kept;
    }

    /**
     * @return Quantity<Unit>
     */
    private function parseExpression(int $minimum, string $expression): Quantity
    {
        $left = $this->parseUnary($expression);

        while (($token = $this->tokens[$this->position] ?? null) !== null) {
            $operator = $token->is(Token::WORD) ? mb_strtolower($token->text) : $token->text;
            [$precedence, $rightAssociative] = self::BINARY[$operator] ?? [null, false];

            if ($precedence === null || $token->is(Token::NUMBER) || $precedence < $minimum) {
                break;
            }

            $this->position++;

            if ($operator === 'divided' && ($this->tokens[$this->position] ?? null)?->text === 'by') {
                $this->position++;
            }

            $right = $this->parseExpression($rightAssociative ? $precedence : $precedence + 1, $expression);
            $left = $this->apply($operator, $left, $right);
        }

        return $left;
    }

    /**
     * @return Quantity<Unit>
     */
    private function parseUnary(string $expression): Quantity
    {
        $token = $this->tokens[$this->position] ?? throw ParseException::cannotParse($expression, 'expression', 'unexpected end');

        if ($token->is(Token::OPERATOR, '-') || $token->is(Token::WORD, 'minus')) {
            $this->position++;

            return $this->parseExpression(self::UNARY_PRECEDENCE, $expression)->negated();
        }

        if ($token->is(Token::OPERATOR, '+')) {
            $this->position++;

            return $this->parseUnary($expression);
        }

        return $this->parsePrimary($expression);
    }

    /**
     * @return Quantity<Unit>
     */
    private function parsePrimary(string $expression): Quantity
    {
        $token = $this->tokens[$this->position++] ?? throw ParseException::cannotParse($expression, 'expression', 'unexpected end');

        if ($token->is(Token::NUMBER)) {
            $value = $this->quantity($token);

            // Adjacent quantities with units add up: "5 ft 11 in", "1 h 30 min", "2 gp 5 sp".
            while ($token->unit !== null && ($next = $this->tokens[$this->position] ?? null)?->is(Token::NUMBER) === true && $next->unit !== null) {
                $this->position++;
                $value = $value->plus($this->inContext($this->quantity($next), $value));
            }

            return $value;
        }

        if ($token->is(Token::OPERATOR, '(')) {
            $value = $this->parseExpression(0, $expression);
            $this->expect(')', $expression);

            return $value;
        }

        if ($token->is(Token::WORD)) {
            return $this->word($token, $expression);
        }

        throw ParseException::cannotParse($expression, 'expression', sprintf('unexpected "%s"', $token->text));
    }

    /**
     * @return Quantity<Unit>
     */
    private function word(Token $token, string $expression): Quantity
    {
        if (isset($this->context->variables[$token->text])) {
            return $this->context->variables[$token->text];
        }

        return match (mb_strtolower($token->text)) {
            'prev', 'ans', 'above' => $this->context->previous ?? throw ParseException::cannotParse($expression, 'expression', 'there is no previous result'),
            'sum', 'total' => $this->total($expression),
            'average', 'avg' => $this->total($expression)->dividedBy(count($this->context->block)),
            'pi', 'π' => $this->plain(BigRational::of(Exact::PI)),
            default => $this->call(mb_strtolower($token->text), $expression),
        };
    }

    /**
     * @return Quantity<Unit>
     */
    private function call(string $function, string $expression): Quantity
    {
        $this->expect('(', $expression);
        $arguments = [$this->parseExpression(0, $expression)];

        while (($this->tokens[$this->position] ?? null)?->is(Token::OPERATOR, ',') === true) {
            $this->position++;
            $arguments[] = $this->parseExpression(0, $expression);
        }

        $this->expect(')', $expression);
        $first = $arguments[0];
        $places = isset($arguments[1]) && in_array($function, ['round', 'floor', 'ceil'], true) ? $this->scalar($arguments[1])->toBigInteger()->toInt() : 0;

        if ($places < 0) {
            throw ParseException::cannotParse($expression, 'expression', sprintf('%s() needs a non-negative number of decimal places', $function));
        }

        $rounded = fn (RoundingMode $mode) => new Quantity($first->value()->toScale($places, $mode)->toBigRational(), $first->unit(), $this->converter);

        return match ($function) {
            'round' => $rounded(RoundingMode::HalfUp),
            'floor' => $rounded(RoundingMode::Floor),
            'ceil' => $rounded(RoundingMode::Ceiling),
            'abs' => $first->abs(),
            'min' => array_reduce($arguments, fn (Quantity $carry, Quantity $q) => $q->isLessThan($carry) ? $q : $carry, $first),
            'max' => array_reduce($arguments, fn (Quantity $carry, Quantity $q) => $q->isGreaterThan($carry) ? $q : $carry, $first),
            'sqrt' => $this->isRatio($first)
                ? $this->plain(Transcendental::power((string) $this->scalar($first), BigRational::of('1/2')))
                : throw ParseException::cannotParse($expression, 'expression', 'sqrt() only takes plain numbers'),
            default => throw ParseException::cannotParse($expression, 'expression', sprintf('unknown function "%s"', $function)),
        };
    }

    /**
     * @param  Quantity<Unit>  $a
     * @param  Quantity<Unit>  $b
     * @return Quantity<Unit>
     */
    private function apply(string $operator, Quantity $a, Quantity $b): Quantity
    {
        return match ($operator) {
            '+', 'plus', 'and' => $this->add($a, $b, 1),
            '-', 'minus' => $this->add($a, $b, -1),
            '*', '×', '·', '⋅', 'times', 'x', 'at', '@' => $this->multiply($a, $b),
            '/', '÷', 'divided' => $this->isRatio($b) && ! $this->isRatio($a) ? $a->dividedBy($this->scalar($b)) : $a->dividedBy($b),
            'of' => $this->isRatio($a) ? $b->multipliedBy($this->scalar($a)) : $this->multiply($a, $b),
            'on' => $b->multipliedBy(BigRational::one()->plus($this->scalar($a))),
            'off' => $b->multipliedBy(BigRational::one()->minus($this->scalar($a))),
            '^' => $this->power($a, $b),
            default => throw ParseException::cannotParse($operator, 'operator'),
        };
    }

    /**
     * @param  Quantity<Unit>  $a
     * @param  Quantity<Unit>  $b
     * @return Quantity<Unit>
     */
    private function add(Quantity $a, Quantity $b, int $sign): Quantity
    {
        // "$55 - 10%" and "$100 + 20%" adjust by a percentage of the left side.
        if ($b->unit() !== Ratio::One && $this->isRatio($b) && ! $this->isRatio($a)) {
            return $a->multipliedBy(BigRational::one()->plus($this->scalar($b)->multipliedBy($sign)));
        }

        // A bare number takes the other side's unit: "5 m + 3" = 8 m.
        if ($b->unit() === Ratio::One && $a->unit() !== Ratio::One) {
            $b = new Quantity($b->value(), $a->unit(), $this->converter);
        } elseif ($a->unit() === Ratio::One && $b->unit() !== Ratio::One) {
            $a = new Quantity($a->value(), $b->unit(), $this->converter);
        }

        $b = $this->inContext($b, $a);
        $a = $this->inContext($a, $b);

        return $sign > 0 ? $a->plus($b) : $a->minus($b);
    }

    /**
     * Re-reads a quantity's unit symbol in the other side's dimension when it
     * doesn't fit as written: in "5 gp + 3 sp", "sp" means silver pieces, not spats.
     *
     * @param  Quantity<Unit>  $quantity
     * @param  Quantity<Unit>  $other
     * @return Quantity<Unit>
     */
    private function inContext(Quantity $quantity, Quantity $other): Quantity
    {
        if (Converter::compatible($quantity->unit(), $other->unit())) {
            return $quantity;
        }

        $unit = $this->converter->units()->tryResolve($quantity->unit()->symbol(), $other->dimension());

        return $unit !== null && Converter::compatible($unit, $other->unit())
            ? new Quantity($quantity->value(), $unit, $this->converter, $quantity->uncertainty(), $quantity->significantFigures())
            : $quantity;
    }

    /**
     * @param  Quantity<Unit>  $a
     * @param  Quantity<Unit>  $b
     * @return Quantity<Unit>
     */
    private function multiply(Quantity $a, Quantity $b): Quantity
    {
        return match (true) {
            $this->isRatio($b) && (! $this->isRatio($a) || $b->unit() !== Ratio::One) && $a->unit() !== Ratio::One => $a->multipliedBy($this->scalar($b)),
            $this->isRatio($a) && $a->unit() !== Ratio::One && ! $this->isRatio($b) => $b->multipliedBy($this->scalar($a)),
            default => $a->multipliedBy($b),
        };
    }

    /**
     * @param  Quantity<Unit>  $base
     * @param  Quantity<Unit>  $exponent
     * @return Quantity<Unit>
     */
    private function power(Quantity $base, Quantity $exponent): Quantity
    {
        $power = $this->scalar($exponent);

        if ($power->getDenominator()->isEqualTo(1)) {
            return $base->power($power->getNumerator()->toInt());
        }

        if (! $this->isRatio($base)) {
            throw ParseException::cannotParse((string) $power, 'exponent', 'only plain numbers can have fractional powers');
        }

        return $this->plain(Transcendental::power((string) $this->scalar($base), $power));
    }

    /**
     * @return Quantity<Unit>
     */
    private function total(string $expression): Quantity
    {
        $block = $this->context->block;
        $first = array_shift($block) ?? throw ParseException::cannotParse($expression, 'expression', 'there is nothing to sum');

        return array_reduce($block, fn (Quantity $carry, Quantity $q) => $carry->plus($q), $first);
    }

    /**
     * @return Quantity<Unit>
     */
    private function quantity(Token $token): Quantity
    {
        $value = $token->value ?? BigRational::zero();

        if ($token->unit === null) {
            return new Quantity($value, Ratio::One, $this->converter, significantFigures: $token->significantFigures);
        }

        $quantity = new Quantity($value, $this->converter->units()->resolve($token->unit), $this->converter, significantFigures: $token->significantFigures);

        // Ingredients become masses so they can be added up: "1/2 cup of flour + 2 tbsp of butter".
        if ($token->substance !== null && $token->substance->density !== null && $quantity->dimension() === Dimension::Volume) {
            return SubstanceConverter::convert($quantity, $token->substance, 'g', $this->converter->units());
        }

        return $quantity;
    }

    /**
     * @return Quantity<Unit>
     */
    private function plain(BigRational $value): Quantity
    {
        return new Quantity($value, Ratio::One, $this->converter);
    }

    /**
     * @param  Quantity<Unit>  $quantity
     */
    private function isRatio(Quantity $quantity): bool
    {
        return $quantity->dimension() === Dimension::Dimensionless;
    }

    /**
     * @param  Quantity<Unit>  $quantity
     */
    private function scalar(Quantity $quantity): BigRational
    {
        return $quantity->to(Ratio::One)->value();
    }

    private function peek(): ?Token
    {
        return $this->tokens[$this->position] ?? null;
    }

    private function expect(string $operator, string $expression): void
    {
        if (! ($this->tokens[$this->position] ?? null)?->is(Token::OPERATOR, $operator)) {
            throw ParseException::cannotParse($expression, 'expression', sprintf('expected "%s"', $operator));
        }

        $this->position++;
    }

    private function isCurrency(string $symbol): bool
    {
        foreach ($this->converter->units()->candidates($symbol) as $unit) {
            if ($unit instanceof Currency) {
                return true;
            }
        }

        return false;
    }
}
