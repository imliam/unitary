<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * Hue in degrees, saturation and lightness as percentages (0-100).
 */
final readonly class Hsl extends SrgbColor
{
    public function __construct(
        public float $hue,
        public float $saturation,
        public float $lightness,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $hue, float $saturation, float $lightness, float $alpha = 1.0): self
    {
        return new self($hue, $saturation, $lightness, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::Hsl;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{hue: float, saturation: float, lightness: float}
     */
    public function channels(): array
    {
        return ['hue' => $this->hue, 'saturation' => $this->saturation, 'lightness' => $this->lightness];
    }

    public function toString(): string
    {
        return sprintf(
            'hsl(%s %s%% %s%%%s)',
            Format::number($this->hue, 2),
            Format::number($this->saturation, 2),
            Format::number($this->lightness, 2),
            Format::alpha($this->alpha),
        );
    }

    protected function toSrgb(): array
    {
        $hue = ColorMath::normalizeHue($this->hue);
        $saturation = $this->saturation / 100;
        $lightness = $this->lightness / 100;
        $a = $saturation * min($lightness, 1 - $lightness);

        $channel = static function (int $n) use ($hue, $lightness, $a): float {
            $k = fmod($n + $hue / 30, 12);

            return $lightness - $a * max(-1, min($k - 3, 9 - $k, 1));
        };

        return [$channel(0), $channel(8), $channel(4)];
    }

    protected static function fromSrgb(array $rgb, float $alpha): static
    {
        [$red, $green, $blue] = $rgb;
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $lightness = ($max + $min) / 2;
        $denominator = min($lightness, 1 - $lightness);
        $saturation = ($max - $min) < 1e-10 || $denominator <= 0 ? 0.0 : ($max - $lightness) / $denominator;

        return new self(ColorMath::srgbHue($red, $green, $blue), $saturation * 100, $lightness * 100, $alpha);
    }
}
