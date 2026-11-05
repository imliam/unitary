<?php

declare(strict_types=1);

namespace Unitary\Units\Novelty;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Masses of familiar things. Base unit: kilogram.
 */
enum NoveltyMass: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case AfricanElephant = 'African elephant';
    case DoubleDeckerBus = 'double-decker bus';
    case BlueWhale = 'blue whale';
    case EiffelTower = 'Eiffel Tower';

    public function dimension(): Dimension
    {
        return Dimension::Mass;
    }

    protected function define(): U
    {
        return match ($this) {
            // WWF: African elephants weigh up to 6 tonnes.
            self::AfricanElephant => U::of('African elephant', 6000)->aliases('elephant', 'elephants'),
            // New Routemaster kerb weight: 12.65 t.
            self::DoubleDeckerBus => U::of('double-decker bus', 12650)->aliases('double decker bus', 'double decker buses', 'London bus', 'London buses', 'Routemaster', 'Routemasters'),
            // NOAA Fisheries: blue whales weigh up to 330,000 lb.
            self::BlueWhale => U::of('blue whale', '330000 * 0.45359237'),
            // Société d'Exploitation de la Tour Eiffel: 10,100 tonnes.
            self::EiffelTower => U::of('Eiffel Tower', 10100000),
        };
    }
}
