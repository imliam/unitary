<?php

declare(strict_types=1);

namespace Unitary\Laravel;

use Brick\Math\BigNumber;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Unitary\Breakdown;
use Unitary\Preferences\UnitPreferences;
use Unitary\Quantity;
use Unitary\UnitDisplay;
use Unitary\Unitary;

/**
 * Backs the @quantity Blade directive.
 *
 * @internal
 */
final class QuantityFormatter
{
    /**
     * With a $usage ("road", "person-height"...), the unit is the one the
     * region uses for it: @quantity($distance, 0, usage: 'road'). With a
     * $display (narrow, short or long), the unit is written in the app's
     * language: @quantity($distance, 0, display: 'long') → "5 kilomètres".
     *
     * @param  \Unitary\Quantity<\Unitary\Unit>|BigNumber|string|null  $quantity
     * @param  non-negative-int|null  $decimals
     */
    public static function format(Quantity|BigNumber|string|null $quantity, ?int $decimals = null, ?string $unit = null, ?string $usage = null, UnitDisplay|string|null $display = null): string
    {
        if ($quantity === null || $quantity === '') {
            return '';
        }

        if (! $quantity instanceof Quantity) {
            $quantity = Unitary::converter()->parseQuantity((string) $quantity);
        }

        if ($unit !== null) {
            $quantity = $quantity->to($unit);
        } elseif ($usage !== null) {
            $quantity = $quantity->forLocale(self::region(), $usage);
        }

        if ($quantity instanceof Breakdown) {
            return $quantity->toString($decimals ?? 0, $display === null ? null : app()->getLocale(), $display);
        }

        return $quantity->format($decimals, locale: app()->getLocale(), display: $display);
    }

    /**
     * The configured region, else the app locale's ("en_GB" → GB).
     */
    public static function region(): string
    {
        $region = app(ConfigRepository::class)->get('unitary.region');

        if (is_string($region) && $region !== '') {
            return $region;
        }

        return UnitPreferences::regionOf(app()->getLocale());
    }
}
