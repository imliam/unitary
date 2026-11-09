<?php

declare(strict_types=1);

namespace Unitary\Calc;

use Brick\Math\BigRational;
use Throwable;
use Unitary\Color\Color;
use Unitary\Converter;
use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Exceptions\ParseException;
use Unitary\Geo\Coordinate;
use Unitary\Quantity;
use Unitary\Scales\Scale;
use Unitary\Time\ZonedTime;
use Unitary\Unit;
use Unitary\Unitary;
use Unitary\Units\Ratio;

/**
 * A notepad-style calculator that understands units, money and percentages:
 *
 *     (5 ft + 30 cm) × 2 in m        → 3.648 m
 *     rate = $120/hr                  → 120 USD/h
 *     42 hr × rate                    → 5040 USD
 *     3 pizzas at $12 each + 20% tip  → 43.2 USD
 *     25% of 300                      → 75
 *     $55 - 10%                       → 49.5 USD
 *     50 is what % of 200             → 25%
 *     sum                             → total of the lines above
 *
 * A Calculator keeps state between lines (variables, `prev`, `sum`), so one
 * instance is one sheet. Unknown words are treated as labels and ignored.
 */
final class Calculator
{
    /** @var array<string, Quantity<Unit>> */
    private array $variables = [];

    /** @var list<Quantity<Unit>> results since the last blank line, for sum/average */
    private array $block = [];

    /** @var Quantity<Unit>|null */
    private ?Quantity $previous = null;

    private readonly Evaluator $evaluator;

    /**
     * @param  bool  $strict  Reject unknown words instead of treating them as labels.
     */
    public function __construct(private readonly ?Converter $converter = null, bool $strict = false)
    {
        $this->evaluator = new Evaluator($this->converter(), $strict);
    }

    /**
     * Evaluates one line. Returns null for blank lines and comments.
     *
     * @return Quantity<Unit>|Color|ZonedTime|Coordinate|Scale|null
     *
     * @throws ParseException|\Unitary\Exceptions\UnitaryException
     */
    public function evaluate(string $line): Quantity|Color|ZonedTime|Coordinate|Scale|null
    {
        $line = trim($line);

        if ($line === '') {
            $this->block = [];

            return null;
        }

        // "# note" and "// note" are comments; "#ff0000" is a color.
        if (preg_match('/^(?:#(?:\s|$)|\/\/)/u', $line)) {
            return null;
        }

        $name = null;

        if (preg_match('/^(?<name>[\p{L}_][\p{L}\p{N}_]*)\s*=\s*(?<expression>.+)$/u', $line, $matches)
            && $this->converter()->units()->tryResolve($matches['name']) === null) {
            $name = $matches['name'];
            $line = $matches['expression'];
        }

        $result = $this->evaluateExpression($line);

        if ($result instanceof Quantity) {
            if ($name !== null) {
                $this->variables[$name] = $result;
            }

            $this->previous = $result;
            $this->block[] = $result;
        }

        return $result;
    }

    /**
     * Evaluates every line of a sheet.
     *
     * @return list<array{line: string, result: Quantity<Unit>|Color|ZonedTime|Coordinate|Scale|null, error: ?string}>
     */
    public function evaluateSheet(string $sheet): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', $sheet) ?: [] as $line) {
            try {
                $rows[] = ['line' => $line, 'result' => $this->evaluate($line), 'error' => null];
            } catch (Throwable $e) {
                $rows[] = ['line' => $line, 'result' => null, 'error' => $e->getMessage()];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, Quantity<Unit>>
     */
    public function variables(): array
    {
        return $this->variables;
    }

    /**
     * @param  Quantity<Unit>  $value
     */
    public function set(string $name, Quantity $value): self
    {
        $this->variables[$name] = $value;

        return $this;
    }

    /**
     * @return Quantity<Unit>|Color|ZonedTime|Coordinate|Scale
     */
    private function evaluateExpression(string $line): Quantity|Color|ZonedTime|Coordinate|Scale
    {
        $context = new Context($this->variables, $this->previous, $this->block);
        $error = null;

        // Plain conversions ("UK 9 shoe in EU") come first, unless the line refers to the sheet's state.
        if (! $this->refersToState($line)) {
            try {
                return $this->converter()->parse($line);
            } catch (ParseException) {
            }
        }

        try {
            return $this->percentPhrase($line, $context) ?? $this->evaluator->evaluateWithTarget($line, $context);
        } catch (ParseException|IncompatibleUnitsException $e) {
            $error = $e;
        }

        // Not arithmetic: colors, times, coordinates, scales, inflation...
        try {
            return $this->converter()->parse($line);
        } catch (ParseException $e) {
            // Without any numbers this wasn't meant as arithmetic, so the general message is clearer.
            throw preg_match('/\d/', $line) ? $error : $e;
        }
    }

    private function refersToState(string $line): bool
    {
        foreach ([...array_keys($this->variables), 'prev', 'ans', 'above', 'sum', 'total', 'average', 'avg'] as $name) {
            if (preg_match('/(?<![\p{L}\p{N}_])'.preg_quote($name, '/').'(?![\p{L}\p{N}_])/iu', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Soulver/Numi-style percentage questions.
     *
     * @return Quantity<Unit>|null
     */
    private function percentPhrase(string $line, Context $context): ?Quantity
    {
        $eval = fn (string $expression) => $this->evaluator->evaluate($expression, $context);
        $percent = fn (BigRational $ratio) => new Quantity($ratio->multipliedBy(100), Ratio::Percent, $this->converter());

        return match (true) {
            // "$50 as a % of $200" → 25%, "50 is what % of 200" → 25%
            (bool) preg_match('/^(?<a>.+?)\s+(?:as\s+(?:a\s+)?|is\s+what\s+)(?:%|percent)\s+of\s+(?<b>.+)$/iu', $line, $m) => $percent($this->ratio($eval($m['a']), $eval($m['b']))),
            // "$70 as a % on $20" → 250%
            (bool) preg_match('/^(?<a>.+?)\s+as\s+(?:a\s+)?(?:%|percent)\s+on\s+(?<b>.+)$/iu', $line, $m) => $percent($this->ratio($eval($m['a']), $eval($m['b']))->minus(1)),
            // "$20 as a % off $70" → 71.43%
            (bool) preg_match('/^(?<a>.+?)\s+as\s+(?:a\s+)?(?:%|percent)\s+off\s+(?<b>.+)$/iu', $line, $m) => $percent(BigRational::one()->minus($this->ratio($eval($m['a']), $eval($m['b'])))),
            // "6 is 5% of what" → 120
            (bool) preg_match('/^(?<a>.+?)\s+is\s+(?<p>.+?)\s+of\s+what$/iu', $line, $m) => $eval($m['a'])->dividedBy($this->scalar($eval($m['p']))),
            // "5% of what is 6" / "5% on what is 6" / "5% off what is 6"
            (bool) preg_match('/^(?<p>.+?)\s+(?<op>of|on|off)\s+what\s+is\s+(?<a>.+)$/iu', $line, $m) => $eval($m['a'])->dividedBy(match (strtolower($m['op'])) {
                'of' => $this->scalar($eval($m['p'])),
                'on' => BigRational::one()->plus($this->scalar($eval($m['p']))),
                default => BigRational::one()->minus($this->scalar($eval($m['p']))),
            }),
            default => null,
        };
    }

    /**
     * @param  Quantity<Unit>  $a
     * @param  Quantity<Unit>  $b
     */
    private function ratio(Quantity $a, Quantity $b): BigRational
    {
        return $this->scalar($a->dividedBy($b));
    }

    /**
     * @param  Quantity<Unit>  $quantity
     */
    private function scalar(Quantity $quantity): BigRational
    {
        return $quantity->to(Ratio::One)->value();
    }

    private function converter(): Converter
    {
        return $this->converter ?? Unitary::converter();
    }
}
