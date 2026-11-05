<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;
use Unitary\Color\Internal\Format;

/**
 * Hue in degrees, whiteness and blackness as percentages (0-100).
 */
final readonly class Hwb extends SrgbColor
{
    public function __construct(
        public float $hue,
        public float $whiteness,
        public float $blackness,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $hue, float $whiteness, float $blackness, float $alpha = 1.0): self
    {
        return new self($hue, $whiteness, $blackness, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::Hwb;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{hue: float, whiteness: float, blackness: float}
     */
    public function channels(): array
    {
        return ['hue' => $this->hue, 'whiteness' => $this->whiteness, 'blackness' => $this->blackness];
    }

    public function toString(): string
    {
        return sprintf(
            'hwb(%s %s%% %s%%%s)',
            Format::number($this->hue, 2),
            Format::number($this->whiteness, 2),
            Format::number($this->blackness, 2),
            Format::alpha($this->alpha),
        );
    }

    protected function toSrgb(): array
    {
        $whiteness = $this->whiteness / 100;
        $blackness = $this->blackness / 100;

        if ($whiteness + $blackness >= 1) {
            $grey = $whiteness / ($whiteness + $blackness);

            return [$grey, $grey, $grey];
        }

        $hue = ColorMath::normalizeHue($this->hue);
        $channel = static function (int $n) use ($hue, $whiteness, $blackness): float {
            $k = fmod($n + $hue / 30, 12);
            $pure = 0.5 - 0.5 * max(-1, min($k - 3, 9 - $k, 1));

            return $pure * (1 - $whiteness - $blackness) + $whiteness;
        };

        return [$channel(0), $channel(8), $channel(4)];
    }

    protected static function fromSrgb(array $rgb, float $alpha): static
    {
        [$red, $green, $blue] = $rgb;

        return new self(
            ColorMath::srgbHue($red, $green, $blue),
            min($red, $green, $blue) * 100,
            (1 - max($red, $green, $blue)) * 100,
            $alpha,
        );
    }
}
