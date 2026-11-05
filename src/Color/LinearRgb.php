<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * sRGB primaries without the gamma curve, channels nominally 0-1.
 */
final readonly class LinearRgb extends Color
{
    public function __construct(
        public float $red,
        public float $green,
        public float $blue,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $red, float $green, float $blue, float $alpha = 1.0): self
    {
        return new self($red, $green, $blue, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::LinearRgb;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{red: float, green: float, blue: float}
     */
    public function channels(): array
    {
        return ['red' => $this->red, 'green' => $this->green, 'blue' => $this->blue];
    }

    public function toString(): string
    {
        return sprintf(
            'color(srgb-linear %s %s %s%s)',
            Format::number($this->red, 5),
            Format::number($this->green, 5),
            Format::number($this->blue, 5),
            Format::alpha($this->alpha),
        );
    }

    protected function toXyzD65(): array
    {
        return ColorMath::multiply(ColorMath::SRGB_TO_XYZ, [$this->red, $this->green, $this->blue]);
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        [$red, $green, $blue] = ColorMath::multiply(ColorMath::xyzToSrgb(), $xyz);

        return new self($red, $green, $blue, $alpha);
    }
}
