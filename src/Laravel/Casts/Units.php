<?php

declare(strict_types=1);

namespace Unitary\Laravel\Casts;

use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use Unitary\Dimension;
use Unitary\Exceptions\UnknownUnitException;
use Unitary\Unit;
use Unitary\Unitary;

/**
 * @internal
 */
final class Units
{
    /**
     * Resolves a stored unit symbol, preferring a unit whose canonical symbol
     * matches exactly so symbols shared with aliases ("C" for coulomb vs the
     * celsius alias) round-trip.
     */
    public static function resolve(string $symbol, ?Dimension $dimension = null): Unit
    {
        $registry = Unitary::converter()->units();
        $candidates = $registry->candidates($symbol);

        foreach ($candidates as $candidate) {
            if ($candidate->symbol() === $symbol && ($dimension === null || $candidate->dimension() === $dimension)) {
                return $candidate;
            }
        }

        return $registry->tryResolve($symbol, $dimension) ?? throw UnknownUnitException::for($symbol);
    }

    public static function dimension(?string $name): ?Dimension
    {
        if ($name === null || $name === '') {
            return null;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', $name));

        foreach (Dimension::cases() as $dimension) {
            if ($dimension->value === $normalized || strtolower($dimension->name) === str_replace('_', '', $normalized)) {
                return $dimension;
            }
        }

        throw new \InvalidArgumentException("Unknown dimension \"{$name}\"");
    }

    /**
     * Undoes the rounding of a stored base value: short exact decimals are kept,
     * anything longer is rounded to 15 significant digits (5 lb stored as
     * 2.26796185 kg comes back as exactly 5 lb).
     */
    public static function restore(BigRational $value): BigRational
    {
        try {
            if ($value->toBigDecimal()->getScale() <= 20) {
                return $value;
            }
        } catch (RoundingNecessaryException) {
        }

        return BigRational::of(Decimal::toString($value, 15));
    }
}
