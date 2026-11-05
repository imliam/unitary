<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Unitary\Exceptions\ParseException;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Support\Exact;
use Unitary\Units\Length;

/**
 * A finger ring size, converted via the inner circumference.
 *
 * Japanese sizes aren't included: JIS publishes them as a table rather than
 * a formula, and charts disagree on the mapping.
 */
final readonly class RingSize implements Scale
{
    private const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private function __construct(
        private BigRational $size,
        public RingSizeSystem $system,
    ) {
    }

    /**
     * @param  float|int|string  $size  A number (US, ISO) or a letter with optional half (UK: "N", "N½", "N 1/2")
     */
    public static function of(float|int|string $size, RingSizeSystem|string|null $system = null): self
    {
        $system = $system === null ? null : ($system instanceof RingSizeSystem ? $system : RingSizeSystem::fromName($system));
        $text = trim((string) $size);

        if (preg_match('/^([A-Z])\s*(½|1\/2|\.5)?$/iu', $text, $matches)) {
            if ($system !== null && $system !== RingSizeSystem::UK) {
                throw ParseException::cannotParse($text, 'ring size', 'letter sizes are UK sizes');
            }

            $index = BigRational::of((int) strpos(self::LETTERS, strtoupper($matches[1])));

            return new self(isset($matches[2]) ? $index->plus('1/2') : $index, RingSizeSystem::UK);
        }

        $value = NumberParser::tryParse($size) ?? throw ParseException::cannotParse($text, 'ring size');

        if ($system === RingSizeSystem::UK) {
            throw ParseException::cannotParse($text, 'ring size', 'UK sizes are letters');
        }

        return new self($value, $system ?? RingSizeSystem::US);
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $circumference
     */
    public static function fromCircumference(Quantity $circumference, RingSizeSystem $system): self
    {
        $mm = $circumference->to(Length::Millimeter)->value();

        $size = match ($system) {
            RingSizeSystem::US => Steps::round(self::diameterFrom($mm)->minus('11.6332')->dividedBy('0.8128'), '1/4'),
            RingSizeSystem::UK => Steps::round($mm->minus('37.5')->dividedBy('1.25'), '1/2'),
            RingSizeSystem::ISO => Steps::round($mm, '1/2'),
        };

        if ($system === RingSizeSystem::UK && ($size->isNegative() || $size->isGreaterThan(25))) {
            throw ParseException::cannotParse((string) $circumference, 'ring size', 'outside the UK A–Z range');
        }

        return new self($size, $system);
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $diameter
     */
    public static function fromDiameter(Quantity $diameter, RingSizeSystem $system): self
    {
        return self::fromCircumference(
            new Quantity($diameter->to(Length::Millimeter)->value()->multipliedBy(Exact::PI), Length::Millimeter),
            $system,
        );
    }

    public function to(RingSizeSystem|string $system): self
    {
        return self::fromCircumference($this->circumference(), $system instanceof RingSizeSystem ? $system : RingSizeSystem::fromName($system));
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function circumference(): Quantity
    {
        $mm = match ($this->system) {
            RingSizeSystem::US => BigRational::of('11.6332')->plus($this->size->multipliedBy('0.8128'))->multipliedBy(Exact::PI),
            RingSizeSystem::UK => BigRational::of('37.5')->plus($this->size->multipliedBy('1.25')),
            RingSizeSystem::ISO => $this->size,
        };

        return new Quantity($mm, Length::Millimeter);
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function diameter(): Quantity
    {
        return new Quantity(self::diameterFrom($this->circumference()->value()), Length::Millimeter);
    }

    public function toString(): string
    {
        if ($this->system === RingSizeSystem::UK) {
            $index = $this->size->toScale(0, RoundingMode::Floor)->toInt();
            $half = ! $this->size->isEqualTo($index);

            return 'UK '.self::LETTERS[$index].($half ? '½' : '');
        }

        return $this->system->value.' '.Steps::format($this->size);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{system: string, size: string, circumference_mm: string, diameter_mm: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'system' => $this->system->value,
            'size' => substr($this->toString(), strlen($this->system->value) + 1),
            'circumference_mm' => (string) $this->circumference()->toDecimal(2),
            'diameter_mm' => (string) $this->diameter()->toDecimal(2),
        ];
    }

    private static function diameterFrom(BigRational $circumference): BigRational
    {
        return $circumference->dividedBy(Exact::PI);
    }
}
