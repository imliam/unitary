<?php

declare(strict_types=1);

namespace Unitary\Units\Novelty;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Lengths of familiar things, for "how many bananas tall is it?" questions.
 * Base unit: meter. Values are published reference figures, not averages of
 * every specimen; each case cites its source.
 */
enum NoveltyLength: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Banana = 'banana';
    case Smoot = 'smoot';
    case Giraffe = 'giraffe';
    case DoubleDeckerBus = 'double-decker bus';
    case BlueWhale = 'blue whale';
    case FootballPitch = 'football pitch';
    case AmericanFootballField = 'football field';
    case BigBen = 'Big Ben';
    case EiffelTower = 'Eiffel Tower';
    case EmpireStateBuilding = 'Empire State Building';
    case BurjKhalifa = 'Burj Khalifa';
    case Marathon = 'marathon';
    case EarthCircumference = 'Earth circumference';
    case LunarDistance = 'lunar distance';

    public function dimension(): Dimension
    {
        return Dimension::Length;
    }

    protected function define(): U
    {
        return match ($this) {
            // USDA FoodData Central "medium" banana: 7 to 7⅞ in long. Uses the 7 in lower bound.
            self::Banana => U::of('banana', '7 * 0.0254'),
            // MIT's Harvard Bridge unit: Oliver Smoot's height, exactly 5 ft 7 in.
            self::Smoot => U::of('smoot', '67 * 0.0254'),
            // Smithsonian's National Zoo: giraffes grow up to 18 ft tall.
            self::Giraffe => U::of('giraffe', '18 * 0.3048')->aliases('giraffe height'),
            // New Routemaster (Wrightbus, TfL), 11.23 m long.
            self::DoubleDeckerBus => U::of('double-decker bus', '11.23')->aliases('double decker bus', 'double decker buses', 'London bus', 'London buses', 'Routemaster', 'Routemasters'),
            // NOAA Fisheries: blue whales grow up to 110 ft long.
            self::BlueWhale => U::of('blue whale', '110 * 0.3048')->aliases('blue whale length'),
            // FIFA Stadium Guidelines recommended pitch: 105 m × 68 m.
            self::FootballPitch => U::of('football pitch', 105)->aliases('soccer field', 'soccer fields', 'soccer pitch', 'soccer pitches')->plural('football pitches'),
            // NFL rulebook: 100 yd plus two 10 yd end zones.
            self::AmericanFootballField => U::of('football field', '120 * 0.9144')->aliases('American football field', 'American football fields', 'gridiron'),
            // UK Parliament: the Elizabeth Tower is 96 m tall.
            self::BigBen => U::of('Big Ben', 96)->aliases('Elizabeth Tower', 'Elizabeth Towers')->plural('Big Bens'),
            // Société d'Exploitation de la Tour Eiffel: 330 m including antennas.
            self::EiffelTower => U::of('Eiffel Tower', 330),
            // CTBUH architectural (roof) height: 381 m.
            self::EmpireStateBuilding => U::of('Empire State Building', 381),
            // CTBUH: 828 m.
            self::BurjKhalifa => U::of('Burj Khalifa', 828),
            // World Athletics: exactly 42.195 km.
            self::Marathon => U::of('marathon', 42195),
            // WGS 84 equatorial circumference: 2π × 6,378,137 m.
            self::EarthCircumference => U::of('Earth circumference', '2 * pi * 6378137')->aliases('circumference of the Earth', 'trip around the Earth', 'trips around the Earth', 'lap of the Earth', 'laps of the Earth')->plural('Earth circumferences'),
            // NASA: average Earth–Moon distance, 384,400 km.
            self::LunarDistance => U::of('lunar distance', 384400000)->aliases('distance to the Moon', 'distance to the moon', 'Earth-Moon distance', 'Earth–Moon distance', 'trip to the Moon', 'trips to the Moon'),
        };
    }
}
