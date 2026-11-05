<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * HSV (also known as HSB): hue in degrees, saturation and value as percentages (0-100).
 */
final readonly class Hsv extends SrgbColor
{
    public function __construct(
        public float $hue,
        public float $saturation,
        public float $value,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $hue, float $saturation, float $value, float $alpha = 1.0): self
    {
        return new self($hue, $saturation, $value, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::Hsv;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{hue: float, saturation: float, value: float}
     */
    public function channels(): array
    {
        return ['hue' => $this->hue, 'saturation' => $this->saturation, 'value' => $this->value];
    }

    /**
     * hsv() is not CSS, but this library can parse it back.
     */
    public function toString(): string
    {
        return sprintf(
            'hsv(%s %s%% %s%%%s)',
            Format::number($this->hue, 2),
            Format::number($this->saturation, 2),
            Format::number($this->value, 2),
            Format::alpha($this->alpha),
        );
    }

    protected function toSrgb(): array
    {
        $hue = ColorMath::normalizeHue($this->hue);
        $saturation = $this->saturation / 100;
        $value = $this->value / 100;

        $channel = static function (int $n) use ($hue, $saturation, $value): float {
            $k = fmod($n + $hue / 60, 6);

            return $value - $value * $saturation * max(0, min($k, 4 - $k, 1));
        };

        return [$channel(5), $channel(3), $channel(1)];
    }

    protected static function fromSrgb(array $rgb, float $alpha): static
    {
        [$red, $green, $blue] = $rgb;
        $value = max($red, $green, $blue);
        $delta = $value - min($red, $green, $blue);
        $saturation = $value <= 0 || $delta < 1e-10 ? 0.0 : $delta / $value;

        return new self(ColorMath::srgbHue($red, $green, $blue), $saturation * 100, $value * 100, $alpha);
    }
}
