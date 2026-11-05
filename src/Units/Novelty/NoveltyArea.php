<?php

declare(strict_types=1);

namespace Unitary\Units\Novelty;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Areas of familiar things, including the journalistic "the size of Wales".
 * Base unit: square meter.
 */
enum NoveltyArea: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case TennisCourt = 'tennis court';
    case BasketballCourt = 'basketball court';
    case FootballPitch = 'football pitch';
    case AmericanFootballField = 'football field';
    case CentralPark = 'Central Park';
    case RhodeIsland = 'Rhode Island';
    case Wales = 'Wales';
    case Belgium = 'Belgium';
    case Texas = 'Texas';

    public function dimension(): Dimension
    {
        return Dimension::Area;
    }

    protected function define(): U
    {
        return match ($this) {
            // ITF Rules of Tennis: a doubles court is 78 ft × 36 ft.
            self::TennisCourt => U::of('tennis court', '78 * 36 * 0.09290304'),
            // NBA court: 94 ft × 50 ft.
            self::BasketballCourt => U::of('basketball court', '94 * 50 * 0.09290304'),
            // FIFA Stadium Guidelines recommended pitch: 105 m × 68 m.
            self::FootballPitch => U::of('football pitch', '105 * 68')->aliases('soccer field', 'soccer fields', 'soccer pitch', 'soccer pitches')->plural('football pitches'),
            // NFL: 120 yd (including end zones) × 53⅓ yd, i.e. 360 ft × 160 ft.
            self::AmericanFootballField => U::of('football field', '360 * 160 * 0.09290304')->aliases('American football field', 'American football fields'),
            // Central Park Conservancy: 843 acres.
            self::CentralPark => U::of('Central Park', '843 * 4046.8564224')->plural('Central Parks'),
            // US Census Bureau: 1,545 sq mi total area.
            self::RhodeIsland => U::of('Rhode Island', '1545 * 2589988.110336')->aliases('size of Rhode Island'),
            // The popular "size of Wales" figure, 20,779 km² (used by e.g. the Size of Wales
            // charity). ONS Standard Area Measurements give 21,218 km² total, 20,737 km² land.
            self::Wales => U::of('Wales', '20779 * 10^6')->aliases('size of Wales')->plural('Wales'),
            // Statbel: 30,689 km².
            self::Belgium => U::of('Belgium', '30689 * 10^6')->aliases('size of Belgium'),
            // US Census Bureau: 268,596 sq mi total area.
            self::Texas => U::of('Texas', '268596 * 2589988.110336')->aliases('size of Texas')->plural('Texas', 'Texases'),
        };
    }
}
