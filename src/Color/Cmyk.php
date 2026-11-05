<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\Format;

/**
 * Naive device CMYK derived directly from sRGB, with channels as percentages (0-100).
 * There is no ICC profile involved, so this is not suitable for print color matching.
 */
final readonly class Cmyk extends SrgbColor
{
    public function __construct(
        public float $cyan,
        public float $magenta,
        public float $yellow,
        public float $key,
        public float $alpha = 1.0,
    ) {}

    public static function of(float $cyan, float $magenta, float $yellow, float $key, float $alpha = 1.0): self
    {
        return new self($cyan, $magenta, $yellow, $key, $alpha);
    }

    public function space(): ColorSpace
    {
        return ColorSpace::Cmyk;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{cyan: float, magenta: float, yellow: float, key: float}
     */
    public function channels(): array
    {
        return ['cyan' => $this->cyan, 'magenta' => $this->magenta, 'yellow' => $this->yellow, 'key' => $this->key];
    }

    public function toString(): string
    {
        return sprintf(
            'cmyk(%s%% %s%% %s%% %s%%%s)',
            Format::number($this->cyan, 2),
            Format::number($this->magenta, 2),
            Format::number($this->yellow, 2),
            Format::number($this->key, 2),
            Format::alpha($this->alpha),
        );
    }

    protected function toSrgb(): array
    {
        $white = 1 - $this->key / 100;

        return [
            (1 - $this->cyan / 100) * $white,
            (1 - $this->magenta / 100) * $white,
            (1 - $this->yellow / 100) * $white,
        ];
    }

    protected static function fromSrgb(array $rgb, float $alpha): static
    {
        $white = max(0.0, min(1.0, max(...$rgb)));

        if ($white <= 0) {
            return new self(0.0, 0.0, 0.0, 100.0, $alpha);
        }

        $ink = static fn (float $channel): float => (1 - max(0.0, min(1.0, $channel)) / $white) * 100;

        return new self($ink($rgb[0]), $ink($rgb[1]), $ink($rgb[2]), (1 - $white) * 100, $alpha);
    }
}
