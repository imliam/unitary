<?php

declare(strict_types=1);

namespace Unitary\Units\Novelty;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Speeds of familiar things. Base unit: meter per second.
 */
enum NoveltySpeed: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case FurlongPerFortnight = 'furlong per fortnight';
    case Snail = 'snail';
    case WalkingPace = 'walking pace';
    case UsainBolt = 'Usain Bolt';
    case Cheetah = 'cheetah';
    case SpeedOfSound = 'speed of sound';

    public function dimension(): Dimension
    {
        return Dimension::Speed;
    }

    protected function define(): U
    {
        return match ($this) {
            // The classic FFF-system joke unit, exact.
            self::FurlongPerFortnight => U::of('furlong per fortnight', '201.168 / 1209600')->aliases('furlongs/fortnight', 'fur/ftn'),
            // Guinness World Records snail race: Archie, 13 in in 2 min (1995).
            self::Snail => U::of('snail', '13 * 0.0254 / 120')->aliases("snail's pace", 'snail pace'),
            // Typical preferred walking speed, 1.4 m/s (Browning et al., J. Appl. Physiol. 2006).
            self::WalkingPace => U::of('walking pace', '1.4')->aliases('walking speed'),
            // Average speed of the 100 m world record: 100 m in 9.58 s (Berlin, 2009).
            self::UsainBolt => U::of('Usain Bolt', '100 / 9.58')->aliases('Bolt'),
            // Smithsonian's National Zoo: cheetahs reach up to 70 mph.
            self::Cheetah => U::of('cheetah', '70 * 1609.344 / 3600'),
            // In dry air at 20 °C: 343 m/s.
            self::SpeedOfSound => U::of('speed of sound', 343)->plural('speed of sound'),
        };
    }
}
