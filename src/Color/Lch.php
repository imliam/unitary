<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * Cylindrical CIELAB (D50): lightness 0-100, chroma, hue in degrees.
 */
final readonly class Lch extends Color
{
    public function __construct(
        public float $lightness,
        public float $chroma,
        public float $hue,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $lightness, float $chroma, float $hue, float $alpha = 1.0): self
    {
        return new self($lightness, $chroma, $hue, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::Lch;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{lightness: float, chroma: float, hue: float}
     */
    public function channels(): array
    {
        return ['lightness' => $this->lightness, 'chroma' => $this->chroma, 'hue' => $this->hue];
    }

    public function toString(): string
    {
        return sprintf(
            'lch(%s%% %s %s%s)',
            Format::number($this->lightness, 2),
            Format::number($this->chroma, 2),
            Format::number($this->hue, 2),
            Format::alpha($this->alpha),
        );
    }

    public function toLab(): Lab
    {
        [$a, $b] = ColorMath::fromPolar($this->chroma, $this->hue);

        return new Lab($this->lightness, $a, $b, $this->alpha);
    }

    protected function toXyzD65(): array
    {
        return $this->toLab()->toXyzD65();
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        $lab = Lab::fromXyzD65($xyz, $alpha);
        [$chroma, $hue] = ColorMath::toPolar($lab->a, $lab->b, 0.0001);

        return new self($lab->lightness, $chroma, $hue, $alpha);
    }
}
