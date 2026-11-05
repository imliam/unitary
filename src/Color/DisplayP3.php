<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * Display P3 (wide gamut, sRGB transfer curve), channels nominally 0-1.
 */
final readonly class DisplayP3 extends Color
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
        return ColorSpace::DisplayP3;
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

    public function isInGamut(float $epsilon = 0.000001): bool
    {
        foreach ($this->channels() as $channel) {
            if ($channel < -$epsilon || $channel > 1 + $epsilon) {
                return false;
            }
        }

        return true;
    }

    public function toString(): string
    {
        return sprintf(
            'color(display-p3 %s %s %s%s)',
            Format::number($this->red, 4),
            Format::number($this->green, 4),
            Format::number($this->blue, 4),
            Format::alpha($this->alpha),
        );
    }

    protected function toXyzD65(): array
    {
        return ColorMath::multiply(ColorMath::P3_TO_XYZ, ColorMath::linearise([$this->red, $this->green, $this->blue]));
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        [$red, $green, $blue] = ColorMath::gammaEncode(ColorMath::multiply(ColorMath::xyzToP3(), $xyz));

        return new self($red, $green, $blue, $alpha);
    }
}
