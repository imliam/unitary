<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Brick\Math\BigRational;
use Unitary\Exceptions\ParseException;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Units\Temperature;

/**
 * UK gas oven marks. Mark 1 is 275 °F and each mark adds 25 °F; below that,
 * ½ is 250 °F and ¼ is 225 °F. Cookbooks often round these (mark 4 ≈ 180 °C).
 */
final readonly class GasMark implements Scale
{
    /** @var list<string> */
    public const MARKS = ['1/4', '1/2', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

    private function __construct(private BigRational $mark)
    {
    }

    public static function of(int|float|string $mark): self
    {
        $value = NumberParser::tryParse($mark);

        foreach (self::MARKS as $known) {
            if ($value !== null && $value->isEqualTo(BigRational::of($known))) {
                return new self($value);
            }
        }

        throw ParseException::cannotParse((string) $mark, 'gas mark', 'marks run from ¼ to 10');
    }

    public static function tryParse(string $input): ?self
    {
        if (! preg_match('/^gas\s*(?:mark|no\.?|regulo)?\s*(\d{1,2}|1\/4|1\/2|¼|½)$/iu', trim($input), $matches)) {
            return null;
        }

        try {
            return self::of(str_replace(['¼', '½'], ['1/4', '1/2'], $matches[1]));
        } catch (ParseException) {
            return null;
        }
    }

    /** The nearest mark to an oven temperature. */
    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $temperature
     */
    public static function fromTemperature(Quantity $temperature): self
    {
        $fahrenheit = $temperature->to(Temperature::Fahrenheit)->value();
        $best = new self(BigRational::of(self::MARKS[0]));

        foreach (self::MARKS as $known) {
            $mark = new self(BigRational::of($known));

            if ($mark->fahrenheit()->minus($fahrenheit)->abs()->isLessThan($best->fahrenheit()->minus($fahrenheit)->abs())) {
                $best = $mark;
            }
        }

        return $best;
    }

    public function mark(): BigRational
    {
        return $this->mark;
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function temperature(): Quantity
    {
        return (new Quantity($this->fahrenheit(), Temperature::Fahrenheit))->to(Temperature::Celsius);
    }

    public function toString(): string
    {
        $mark = match ((string) $this->mark) {
            '1/4' => '¼',
            '1/2' => '½',
            default => (string) $this->mark,
        };

        return "Gas mark {$mark}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{mark: string, celsius: string, fahrenheit: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'mark' => (string) $this->mark,
            'celsius' => (string) $this->temperature()->toDecimal(1),
            'fahrenheit' => (string) $this->fahrenheit(),
        ];
    }

    private function fahrenheit(): BigRational
    {
        return $this->mark->isLessThan(1)
            ? BigRational::of(200)->plus($this->mark->multipliedBy(100))
            : BigRational::of(250)->plus($this->mark->multipliedBy(25));
    }
}
