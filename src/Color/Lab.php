<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * CIELAB relative to D50, as used by CSS lab(). Lightness is 0-100.
 */
final readonly class Lab extends Color
{
    private const EPSILON = 216 / 24389;

    private const KAPPA = 24389 / 27;

    public function __construct(
        public float $lightness,
        public float $a,
        public float $b,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $lightness, float $a, float $b, float $alpha = 1.0): self
    {
        return new self($lightness, $a, $b, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::Lab;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{lightness: float, a: float, b: float}
     */
    public function channels(): array
    {
        return ['lightness' => $this->lightness, 'a' => $this->a, 'b' => $this->b];
    }

    public function toString(): string
    {
        return sprintf(
            'lab(%s%% %s %s%s)',
            Format::number($this->lightness, 2),
            Format::number($this->a, 2),
            Format::number($this->b, 2),
            Format::alpha($this->alpha),
        );
    }

    protected function toXyzD65(): array
    {
        $fy = ($this->lightness + 16) / 116;
        $fx = $this->a / 500 + $fy;
        $fz = $fy - $this->b / 200;

        $inverse = static fn (float $f): float => $f ** 3 > self::EPSILON ? $f ** 3 : (116 * $f - 16) / self::KAPPA;

        $d50 = [
            $inverse($fx) * ColorMath::D50[0],
            ($this->lightness > self::KAPPA * self::EPSILON ? $fy ** 3 : $this->lightness / self::KAPPA) * ColorMath::D50[1],
            $inverse($fz) * ColorMath::D50[2],
        ];

        return ColorMath::multiply(ColorMath::d50ToD65(), $d50);
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        $d50 = ColorMath::multiply(ColorMath::d65ToD50(), $xyz);

        $f = static fn (float $value): float => $value > self::EPSILON ? $value ** (1 / 3) : (self::KAPPA * $value + 16) / 116;

        $fx = $f($d50[0] / ColorMath::D50[0]);
        $fy = $f($d50[1] / ColorMath::D50[1]);
        $fz = $f($d50[2] / ColorMath::D50[2]);

        return new self(116 * $fy - 16, 500 * ($fx - $fy), 200 * ($fy - $fz), $alpha);
    }
}
