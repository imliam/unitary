<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Exceptions\ParseException;

/**
 * 8-bit sRGB written as hex. Converting into this space rounds and clamps each channel to 0-255,
 * so out-of-gamut colors are clipped.
 */
final readonly class Hex extends SrgbColor
{
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
        public float $alpha = 1.0,
    ) {}

    /**
     * Accepts #rgb, #rgba, #rrggbb or #rrggbbaa (the leading # is optional here).
     *
     * @throws ParseException
     */
    public static function fromString(string $hex): self
    {
        $digits = ltrim(trim($hex), '#');

        if (! ctype_xdigit($digits) || ! in_array(strlen($digits), [3, 4, 6, 8], true)) {
            throw ParseException::cannotParse($hex, 'hex color');
        }

        if (strlen($digits) <= 4) {
            $digits = implode('', array_map(static fn (string $digit): string => $digit.$digit, str_split($digits)));
        }

        return new self(
            (int) hexdec(substr($digits, 0, 2)),
            (int) hexdec(substr($digits, 2, 2)),
            (int) hexdec(substr($digits, 4, 2)),
            strlen($digits) === 8 ? hexdec(substr($digits, 6, 2)) / 255 : 1.0,
        );
    }

    public function space(): ColorSpace
    {
        return ColorSpace::Hex;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }

    /**
     * @return array{red: int, green: int, blue: int}
     */
    public function channels(): array
    {
        return ['red' => $this->red, 'green' => $this->green, 'blue' => $this->blue];
    }

    public function toString(): string
    {
        $hex = sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);

        return $this->alpha < 1.0 ? $hex.sprintf('%02x', (int) round(max(0.0, $this->alpha) * 255)) : $hex;
    }

    protected function toSrgb(): array
    {
        return [$this->red / 255, $this->green / 255, $this->blue / 255];
    }

    protected static function fromSrgb(array $rgb, float $alpha): static
    {
        $byte = static fn (float $channel): int => (int) round(max(0.0, min(1.0, $channel)) * 255);

        return new self($byte($rgb[0]), $byte($rgb[1]), $byte($rgb[2]), $alpha);
    }
}
