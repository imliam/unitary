<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\Format;

/**
 * CIE XYZ relative to the D65 white point, with Y = 1 for reference white.
 */
final readonly class Xyz extends Color
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
        return ColorSpace::Xyz;
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
            'color(xyz-d65 %s %s %s%s)',
            Format::number($this->x, 5),
            Format::number($this->y, 5),
            Format::number($this->z, 5),
            Format::alpha($this->alpha),
        );
    }

    protected function toXyzD65(): array
    {
        return [$this->x, $this->y, $this->z];
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        return new self($xyz[0], $xyz[1], $xyz[2], $alpha);
    }
}
