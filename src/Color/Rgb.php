<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\Format;

/**
 * sRGB with channels nominally 0-255. Values outside that range (out of gamut) are preserved.
 */
final readonly class Rgb extends SrgbColor
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
        return ColorSpace::Rgb;
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

    public function isInGamut(float $epsilon = 0.0001): bool
    {
        foreach ($this->channels() as $channel) {
            if ($channel < -$epsilon || $channel > 255 + $epsilon) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clips each channel into 0-255.
     */
    public function clamp(): self
    {
        $clamp = static fn (float $channel): float => max(0.0, min(255.0, $channel));

        return new self($clamp($this->red), $clamp($this->green), $clamp($this->blue), $this->alpha);
    }

    public function toString(): string
    {
        return sprintf(
            'rgb(%s %s %s%s)',
            Format::number($this->red, 2),
            Format::number($this->green, 2),
            Format::number($this->blue, 2),
            Format::alpha($this->alpha),
        );
    }

    protected function toSrgb(): array
    {
        return [$this->red / 255, $this->green / 255, $this->blue / 255];
    }

    protected static function fromSrgb(array $rgb, float $alpha): static
    {
        return new self($rgb[0] * 255, $rgb[1] * 255, $rgb[2] * 255, $alpha);
    }
}
