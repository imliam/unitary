<?php

declare(strict_types=1);

namespace Unitary;

/**
 * Measurement systems for humanize() and breakdown(). Imperial and US
 * customary share length and weight units but differ for volume and tons.
 */
enum UnitSystem: string
{
    case Metric = 'metric';
    case Imperial = 'imperial';
    case UsCustomary = 'us';

    private const IMPERIAL_ONLY = ['st', 'LT', 'cwt', 'imp gal', 'imp qt', 'imp pt', 'imp fl oz', 'imp gi', 'imp tbsp', 'imp tsp', 'mpg (imp)'];

    private const US_ONLY = ['gal', 'qt', 'pint', 'cup', 'fl oz', 'tbsp', 'tsp', 'gi', 'ton', 'sh cwt', 'mpg', 'bu', 'bbl'];

    private const SHARED = [
        'in', 'ft', 'yd', 'mi', 'mil', 'fur', 'ch', 'lea', 'ftm', 'oz', 'lb', 'gr', 'dr', 'ozt', 'lbt', 'dwt', 'slug',
        'in²', 'ft²', 'yd²', 'mi²', 'ac', 'rood', 'in³', 'ft³', 'yd³', 'ac⋅ft', '°F', '°R', 'Δ°F', 'Δ°R', 'mph', 'ft/s', 'in/s', 'ft/s²',
        'lbf', 'pdl', 'kip', 'psi', 'ksi', 'inHg', 'inH₂O', 'BTU', 'thm', 'ft⋅lbf', 'hp', 'BTU/h', 'lbf⋅ft', 'lbf⋅in',
        'lb/ft³', 'lb/in³', 'lb/gal', 'gal/min', 'gal/h', 'ft³/min', 'ft³/s', 'fc', 'kn', 'nmi',
    ];

    /** The system a unit belongs to, or null for units used everywhere (seconds, currencies). */
    public static function of(Unit $unit): ?self
    {
        $symbol = $unit->symbol();

        return match (true) {
            in_array($symbol, self::IMPERIAL_ONLY, true) => self::Imperial,
            in_array($symbol, self::US_ONLY, true), in_array($symbol, self::SHARED, true) => self::UsCustomary,
            $unit instanceof MeasurementUnit && ! in_array($unit->dimension(), [Dimension::Time, Dimension::Currency, Dimension::Angle], true) => self::Metric,
            default => null,
        };
    }
}
