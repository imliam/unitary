<?php

declare(strict_types=1);

namespace Unitary\Color;

use JsonSerializable;
use Stringable;
use Unitary\Color\Internal\ColorParser;
use Unitary\Color\Internal\NamedColors;
use Unitary\Exceptions\ParseException;

/**
 * A color in a specific color space. Instances are immutable; converting returns a new instance.
 *
 * Conversions between sRGB-based spaces (hex, rgb, hsl, hsv, hwb, cmyk) are direct; everything else
 * goes through CIE XYZ (D65) using the CSS Color Level 4 maths. Values are never gamut-mapped,
 * except by {@see Hex}, which clamps each channel to 0-255.
 */
abstract readonly class Color implements JsonSerializable, Stringable
{
    /**
     * @throws ParseException
     */
    public static function parse(string $input): Color
    {
        return ColorParser::parse($input);
    }

    public static function tryParse(string $input): ?Color
    {
        try {
            return ColorParser::parse($input);
        } catch (ParseException) {
            return null;
        }
    }

    abstract public function space(): ColorSpace;

    abstract public function alpha(): float;

    /**
     * The color's channels (excluding alpha), keyed by name.
     *
     * @return array<string, float|int>
     */
    abstract public function channels(): array;

    /**
     * CSS representation of the color.
     */
    abstract public function toString(): string;

    /**
     * @return array{float, float, float}
     */
    abstract protected function toXyzD65(): array;

    /**
     * @param  array{float, float, float}  $xyz
     */
    abstract protected static function fromXyzD65(array $xyz, float $alpha): static;

    /**
     * @throws ParseException when given an unknown color space name
     */
    public function to(ColorSpace|string $space): Color
    {
        $space = is_string($space) ? ColorSpace::fromName($space) : $space;

        return $this->convertTo($space->colorClass());
    }

    public function toHex(): Hex
    {
        return $this->convertTo(Hex::class);
    }

    public function toRgb(): Rgb
    {
        return $this->convertTo(Rgb::class);
    }

    public function toLinearRgb(): LinearRgb
    {
        return $this->convertTo(LinearRgb::class);
    }

    public function toHsl(): Hsl
    {
        return $this->convertTo(Hsl::class);
    }

    public function toHsv(): Hsv
    {
        return $this->convertTo(Hsv::class);
    }

    public function toHwb(): Hwb
    {
        return $this->convertTo(Hwb::class);
    }

    public function toCmyk(): Cmyk
    {
        return $this->convertTo(Cmyk::class);
    }

    public function toXyz(): Xyz
    {
        return $this->convertTo(Xyz::class);
    }

    public function toXyzD50(): XyzD50
    {
        return $this->convertTo(XyzD50::class);
    }

    public function toLab(): Lab
    {
        return $this->convertTo(Lab::class);
    }

    public function toLch(): Lch
    {
        return $this->convertTo(Lch::class);
    }

    public function toOklab(): Oklab
    {
        return $this->convertTo(Oklab::class);
    }

    public function toOklch(): Oklch
    {
        return $this->convertTo(Oklch::class);
    }

    public function toDisplayP3(): DisplayP3
    {
        return $this->convertTo(DisplayP3::class);
    }

    /**
     * The CSS color name matching this color exactly (after rounding to 8-bit hex), if any.
     */
    public function toName(): ?string
    {
        $hex = $this->toHex();

        if ($hex->alpha === 0.0) {
            return 'transparent';
        }

        if ($hex->alpha < 1.0) {
            return null;
        }

        $name = array_search(substr($hex->toString(), 1), NamedColors::COLORS, true);

        return $name === false ? null : $name;
    }

    /**
     * Compares two colors in unclamped sRGB, where the tolerance is measured in 0-255 channel units.
     */
    public function equals(Color $other, float $tolerance = 0.5): bool
    {
        $a = $this->toRgb();
        $b = $other->toRgb();

        return abs($a->red - $b->red) <= $tolerance
            && abs($a->green - $b->green) <= $tolerance
            && abs($a->blue - $b->blue) <= $tolerance
            && abs($a->alpha - $b->alpha) <= $tolerance / 255;
    }

    /**
     * @return array<string, string|float|int>
     */
    public function jsonSerialize(): array
    {
        return ['space' => $this->space()->value, ...$this->channels(), 'alpha' => $this->alpha()];
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @template T of Color
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function convertTo(string $class): Color
    {
        if ($this instanceof $class) {
            return $this;
        }

        if ($this instanceof SrgbColor && is_a($class, SrgbColor::class, true)) {
            return $class::fromSrgb($this->toSrgb(), $this->alpha());
        }

        return $class::fromXyzD65($this->toXyzD65(), $this->alpha());
    }
}
