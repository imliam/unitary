<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * Oklab (Björn Ottosson): perceptual lightness 0-1 and a/b axes, roughly -0.4 to 0.4.
 */
final readonly class Oklab extends Color
{
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
        return ColorSpace::Oklab;
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
            'oklab(%s%% %s %s%s)',
            Format::number($this->lightness * 100, 2),
            Format::number($this->a, 4),
            Format::number($this->b, 4),
            Format::alpha($this->alpha),
        );
    }

    protected function toXyzD65(): array
    {
        [$l, $m, $s] = ColorMath::multiply(ColorMath::oklabToLms(), [$this->lightness, $this->a, $this->b]);

        return ColorMath::multiply(ColorMath::lmsToXyz(), [$l ** 3, $m ** 3, $s ** 3]);
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        $cbrt = static fn (float $value): float => $value < 0 ? -((-$value) ** (1 / 3)) : $value ** (1 / 3);
        [$l, $m, $s] = ColorMath::multiply(ColorMath::xyzToLms(), $xyz);
        [$lightness, $a, $b] = ColorMath::multiply(ColorMath::LMS_TO_OKLAB, [$cbrt($l), $cbrt($m), $cbrt($s)]);

        return new self($lightness, $a, $b, $alpha);
    }
}
