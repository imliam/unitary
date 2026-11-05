<?php

declare(strict_types=1);

namespace Unitary\Units\Novelty;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Volumes of familiar things. Base unit: cubic meter.
 */
enum NoveltyVolume: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Shot = 'shot';
    case SodaCan = 'soda can';
    case WineBottle = 'wine bottle';
    case Bathtub = 'bathtub';
    case OlympicSwimmingPool = 'Olympic swimming pool';

    public function dimension(): Dimension
    {
        return Dimension::Volume;
    }

    protected function define(): U
    {
        return match ($this) {
            // US standard shot / jigger: 1.5 US fl oz.
            self::Shot => U::of('shot', '1.5 * 0.0000295735295625')->aliases('shot glass', 'shot glasses', 'jigger', 'jiggers'),
            // US standard can: 12 US fl oz.
            self::SodaCan => U::of('soda can', '12 * 0.0000295735295625')->aliases('can of soda', 'cans of soda', 'can of coke', 'cans of coke', 'soda cans'),
            // EU Directive 2007/45/EC and US TTB standard fill: 750 mL.
            self::WineBottle => U::of('wine bottle', '0.00075')->aliases('bottle of wine', 'bottles of wine'),
            // Waterwise / Energy Saving Trust: a typical bath uses about 80 L.
            self::Bathtub => U::of('bathtub', '0.08')->aliases('bath', 'baths', 'bathful', 'bathfuls', 'bathtubful'),
            // World Aquatics (FINA) Olympic pool: 50 m × 25 m × 2 m minimum depth.
            self::OlympicSwimmingPool => U::of('Olympic swimming pool', '50 * 25 * 2')->aliases('Olympic pool', 'Olympic pools', 'Olympic-size swimming pool', 'Olympic-size swimming pools', 'Olympic-sized swimming pool', 'Olympic-sized swimming pools'),
        };
    }
}
