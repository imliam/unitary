<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * CIE XYZ relative to the D50 white point (Bradford-adapted from D65).
 */
final readonly class XyzD50 extends Color
{
    public function __construct(
        public float $x,
        public float $y,
        public float $z,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $x, float $y, float $z, float $alpha = 1.0): self
    {
        return new self($x, $y, $z, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::XyzD50;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{x: float, y: float, z: float}
     */
    public function channels(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'z' => $this->z];
    }

    public function toString(): string
    {
        return sprintf(
            'color(xyz-d50 %s %s %s%s)',
            Format::number($this->x, 5),
            Format::number($this->y, 5),
            Format::number($this->z, 5),
            Format::alpha($this->alpha),
        );
    }

    protected function toXyzD65(): array
    {
        return ColorMath::multiply(ColorMath::d50ToD65(), [$this->x, $this->y, $this->z]);
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        [$x, $y, $z] = ColorMath::multiply(ColorMath::d65ToD50(), $xyz);

        return new self($x, $y, $z, $alpha);
    }
}
