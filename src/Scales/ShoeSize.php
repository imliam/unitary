<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Brick\Math\BigRational;
use Unitary\Exceptions\ParseException;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Units\Length;

/**
 * A shoe size in one of the common systems, converted via the shoe last length
 * (UK/US/EU) or the foot length (Mondopoint/JP).
 *
 * The last is taken to be two barleycorns (⅔ in) longer than the foot, the
 * allowance conventionally used to relate the barleycorn and Paris point
 * scales to foot length. Real shoes vary by manufacturer; treat conversions as
 * the nominal values the formulas give.
 */
final readonly class ShoeSize implements Scale
{
    /** Two barleycorns, in inches. */
    private const ALLOWANCE_INCHES = '2/3';

    private BigRational $size;

    private function __construct(
        BigRational $size,
        public ShoeSizeSystem $system,
        public ShoeFit $fit,
    ) {
        $this->size = $size;
    }

    public static function of(float|int|string $size, ShoeSizeSystem|string $system, ShoeFit $fit = ShoeFit::Men): self
    {
        $value = NumberParser::tryParse($size)
            ?? throw ParseException::cannotParse((string) $size, 'shoe size');

        return new self($value, $system instanceof ShoeSizeSystem ? $system : ShoeSizeSystem::fromName($system), $fit);
    }

    /** Builds the nearest size in a system from a last length. */
    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $lastLength
     */
    public static function fromLastLength(Quantity $lastLength, ShoeSizeSystem $system, ShoeFit $fit = ShoeFit::Men): self
    {
        $inches = $lastLength->to(Length::Inch)->value();
        $cm = $lastLength->to(Length::Centimeter)->value();

        $size = match ($system) {
            ShoeSizeSystem::UK => $fit === ShoeFit::Children ? $inches->multipliedBy(3)->minus(12) : $inches->multipliedBy(3)->minus(25),
            ShoeSizeSystem::US => $inches->multipliedBy(3)->minus(match ($fit) {
                ShoeFit::Men => '24',
                ShoeFit::Women => '22.5',
                ShoeFit::Children => '35/3',
            }),
            ShoeSizeSystem::EU => $cm->multipliedBy('3/2'),
            ShoeSizeSystem::Mondopoint, ShoeSizeSystem::JP => self::footFromLast($lastLength)
                ->to(Length::Millimeter)->value()->dividedBy($system === ShoeSizeSystem::JP ? 10 : 1),
        };

        return new self(Steps::round($size, self::step($system)), $system, $fit);
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $footLength
     */
    public static function fromFootLength(Quantity $footLength, ShoeSizeSystem $system, ShoeFit $fit = ShoeFit::Men): self
    {
        return self::fromLastLength(self::lastFromFoot($footLength), $system, $fit);
    }

    public function to(ShoeSizeSystem|string $system, ?ShoeFit $fit = null): self
    {
        $system = $system instanceof ShoeSizeSystem ? $system : ShoeSizeSystem::fromName($system);

        return self::fromLastLength($this->lastLength(), $system, $fit ?? $this->fit);
    }

    public function size(): BigRational
    {
        return $this->size;
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function lastLength(): Quantity
    {
        $size = $this->size;

        return match ($this->system) {
            ShoeSizeSystem::UK => self::inches($size->plus($this->fit === ShoeFit::Children ? 12 : 25)->dividedBy(3)),
            ShoeSizeSystem::US => self::inches($size->plus(match ($this->fit) {
                ShoeFit::Men => '24',
                ShoeFit::Women => '22.5',
                ShoeFit::Children => '35/3',
            })->dividedBy(3)),
            ShoeSizeSystem::EU => (new Quantity($size->multipliedBy('2/3'), Length::Centimeter))->to(Length::Millimeter),
            ShoeSizeSystem::Mondopoint => self::lastFromFoot(new Quantity($size, Length::Millimeter)),
            ShoeSizeSystem::JP => self::lastFromFoot(new Quantity($size->multipliedBy(10), Length::Millimeter)),
        };
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function footLength(): Quantity
    {
        return self::footFromLast($this->lastLength());
    }

    public function toString(): string
    {
        $fit = match (true) {
            $this->fit === ShoeFit::Children => ' (children)',
            $this->fit === ShoeFit::Women && $this->system === ShoeSizeSystem::US => ' (women)',
            default => '',
        };

        return $this->system->value.' '.Steps::format($this->size).$fit;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{system: string, size: string, fit: string, foot_length_mm: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'system' => $this->system->value,
            'size' => Steps::format($this->size),
            'fit' => $this->fit->value,
            'foot_length_mm' => (string) $this->footLength()->to(Length::Millimeter)->toDecimal(1),
        ];
    }

    private static function step(ShoeSizeSystem $system): string
    {
        return match ($system) {
            ShoeSizeSystem::Mondopoint => '5',
            ShoeSizeSystem::JP => '1/2',
            default => '1/2',
        };
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    private static function inches(BigRational $inches): Quantity
    {
        return (new Quantity($inches, Length::Inch))->to(Length::Millimeter);
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $foot
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    private static function lastFromFoot(Quantity $foot): Quantity
    {
        return $foot->to(Length::Millimeter)->plus(new Quantity(self::ALLOWANCE_INCHES, Length::Inch));
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $last
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    private static function footFromLast(Quantity $last): Quantity
    {
        return $last->to(Length::Millimeter)->minus(new Quantity(self::ALLOWANCE_INCHES, Length::Inch));
    }
}
