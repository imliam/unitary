<?php

declare(strict_types=1);

namespace Unitary\Laravel\Casts;

use Unitary\Dimension;
use Unitary\Unit;

/**
 * Readable cast definitions for a model's casts() method:
 *
 *     'weight' => AsQuantity::of(Mass::Kilogram),
 *     'height' => AsQuantity::withUnit(Dimension::Length),
 */
final class AsQuantity
{
    /** A single column in a fixed unit. */
    public static function of(Unit $unit): string
    {
        return QuantityCast::class.':'.$unit->symbol().','.$unit->dimension()->value;
    }

    /** A base-unit value column plus a unit column ({attribute}_value / {attribute}_unit by default). */
    public static function withUnit(?Dimension $dimension = null, ?string $valueColumn = null, ?string $unitColumn = null): string
    {
        return QuantityWithUnitCast::class.':'.implode(',', [$dimension->value ?? '', $valueColumn ?? '', $unitColumn ?? '']);
    }
}
