<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Unitary\Exceptions\ParseException;
use Unitary\Quantity;
use Unitary\Units\Area;
use Unitary\Units\Length;

/**
 * American Wire Gauge (ASTM B258): d(n) = 0.005 in × 92^((36 − n) / 39),
 * from 0000 (4/0, n = −3) to 40.
 */
final readonly class WireGauge implements Scale
{
    public const MIN = -3;

    public const MAX = 40;

    private function __construct(public int $gauge)
    {
    }

    /**
     * @param  int|string  $gauge  12, "12", "0", "00", "4/0", "0000"
     */
    public static function of(int|string $gauge): self
    {
        $number = is_int($gauge) ? $gauge : self::parseGauge($gauge);

        if ($number === null || $number < self::MIN || $number > self::MAX) {
            throw ParseException::cannotParse((string) $gauge, 'wire gauge', 'AWG runs from 4/0 to 40');
        }

        return new self($number);
    }

    public static function tryParse(string $input): ?self
    {
        if (! preg_match('/^(?:(?:awg|gauge|ga)\s*#?\s*(?<a>\d{1,2}|0{1,4}|[1-4]\/0)|#?(?<b>\d{1,2}|0{1,4}|[1-4]\/0)\s*(?:-|\s)?(?:awg|gauge|ga\.?))$/i', trim($input), $matches)) {
            return null;
        }

        $gauge = self::parseGauge($matches['b'] ?? $matches['a']);

        return $gauge !== null && $gauge >= self::MIN && $gauge <= self::MAX ? new self($gauge) : null;
    }

    /** The nearest gauge to a diameter. */
    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $diameter
     */
    public static function fromDiameter(Quantity $diameter): self
    {
        $inches = $diameter->to(Length::Inch)->toFloat();

        if ($inches <= 0) {
            throw ParseException::cannotParse((string) $diameter, 'wire gauge', 'diameter must be positive');
        }

        $gauge = (int) round(36 - 39 * log($inches / 0.005, 92));

        return new self(max(self::MIN, min(self::MAX, $gauge)));
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function diameter(): Quantity
    {
        return (new Quantity(0.005 * 92 ** ((36 - $this->gauge) / 39), Length::Inch))->to(Length::Millimeter);
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function crossSectionalArea(): Quantity
    {
        $radius = $this->diameter()->toFloat() / 2;

        return new Quantity(M_PI * $radius * $radius, Area::SquareMillimeter);
    }

    public function toString(): string
    {
        return ($this->gauge <= 0 ? (1 - $this->gauge).'/0' : (string) $this->gauge).' AWG';
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{gauge: string, diameter_mm: string, area_mm2: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'gauge' => substr($this->toString(), 0, -4),
            'diameter_mm' => (string) $this->diameter()->toDecimal(4),
            'area_mm2' => (string) $this->crossSectionalArea()->toDecimal(4),
        ];
    }

    private static function parseGauge(string $gauge): ?int
    {
        $gauge = trim($gauge);

        return match (true) {
            (bool) preg_match('/^[1-4]\/0$/', $gauge) => 1 - (int) $gauge[0],
            (bool) preg_match('/^0{2,4}$/', $gauge) => 1 - strlen($gauge),
            (bool) preg_match('/^\d{1,2}$/', $gauge) => (int) $gauge,
            default => null,
        };
    }
}
