<?php

declare(strict_types=1);

namespace Unitary\Color;

use Unitary\Color\Internal\ColorMath;

/**
 * Base for spaces that are re-parameterisations of gamma-encoded sRGB, so they can convert
 * between each other without a round trip through XYZ.
 */
abstract readonly class SrgbColor extends Color
{
    /**
     * Gamma-encoded sRGB channels, nominally 0-1.
     *
     * @return array{float, float, float}
     */
    abstract protected function toSrgb(): array;

    /**
     * @param  array{float, float, float}  $rgb
     */
    abstract protected static function fromSrgb(array $rgb, float $alpha): static;

    protected function toXyzD65(): array
    {
        return ColorMath::multiply(ColorMath::SRGB_TO_XYZ, ColorMath::linearise($this->toSrgb()));
    }

    protected static function fromXyzD65(array $xyz, float $alpha): static
    {
        return static::fromSrgb(ColorMath::gammaEncode(ColorMath::multiply(ColorMath::xyzToSrgb(), $xyz)), $alpha);
    }
}
