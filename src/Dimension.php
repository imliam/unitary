<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigRational;
use Unitary\Algebra\DimensionVector;

/**
 * A physical (or otherwise measurable) quantity. Units can only be converted
 * between one another when they share a dimension.
 */
enum Dimension: string
{
    case Length = 'length';
    case Mass = 'mass';
    case Time = 'time';
    case Temperature = 'temperature';

    /** A change in temperature: Δ°C, Δ°F. Linear, unlike temperature itself. */
    case TemperatureDifference = 'temperature_difference';
    case Area = 'area';
    case Volume = 'volume';
    case Speed = 'speed';
    case Acceleration = 'acceleration';
    case Force = 'force';
    case Pressure = 'pressure';
    case Energy = 'energy';
    case Power = 'power';
    case Torque = 'torque';
    case Frequency = 'frequency';
    case Angle = 'angle';
    case SolidAngle = 'solid_angle';
    case DigitalStorage = 'digital_storage';
    case DataRate = 'data_rate';
    case ElectricCurrent = 'electric_current';
    case Voltage = 'voltage';
    case ElectricResistance = 'electric_resistance';
    case ElectricConductance = 'electric_conductance';
    case ElectricCharge = 'electric_charge';
    case Capacitance = 'capacitance';
    case Inductance = 'inductance';
    case MagneticFlux = 'magnetic_flux';
    case MagneticFluxDensity = 'magnetic_flux_density';
    case AmountOfSubstance = 'amount_of_substance';
    case MolarConcentration = 'molar_concentration';
    case CatalyticActivity = 'catalytic_activity';
    case LuminousIntensity = 'luminous_intensity';
    case LuminousFlux = 'luminous_flux';
    case Illuminance = 'illuminance';
    case Radioactivity = 'radioactivity';
    case AbsorbedDose = 'absorbed_dose';
    case EquivalentDose = 'equivalent_dose';
    case Density = 'density';
    case VolumetricFlowRate = 'volumetric_flow_rate';
    case FuelEconomy = 'fuel_economy';
    case Currency = 'currency';

    /** Tabletop RPG coinage (copper, silver, electrum, gold and platinum pieces). */
    case FantasyCurrency = 'fantasy_currency';
    case Dimensionless = 'dimensionless';

    /** A product or quotient of units with no named dimension, e.g. m⋅ft⋅s or $/kWh. */
    case Derived = 'derived';

    public function label(): string
    {
        return str_replace('_', ' ', $this->value);
    }

    /**
     * The dimension as powers of base dimensions. Derived has no fixed vector.
     */
    public function vector(): ?DimensionVector
    {
        $exponents = match ($this) {
            self::Length => ['L' => 1],
            self::Mass => ['M' => 1],
            self::Time => ['T' => 1],
            self::Temperature, self::TemperatureDifference => ['Θ' => 1],
            self::Area => ['L' => 2],
            self::Volume => ['L' => 3],
            self::Speed => ['L' => 1, 'T' => -1],
            self::Acceleration => ['L' => 1, 'T' => -2],
            self::Force => ['M' => 1, 'L' => 1, 'T' => -2],
            self::Pressure => ['M' => 1, 'L' => -1, 'T' => -2],
            self::Energy, self::Torque => ['M' => 1, 'L' => 2, 'T' => -2],
            self::Power => ['M' => 1, 'L' => 2, 'T' => -3],
            self::Frequency, self::Radioactivity => ['T' => -1],
            self::Angle => ['A' => 1],
            self::SolidAngle => ['A' => 2],
            self::DigitalStorage => ['B' => 1],
            self::DataRate => ['B' => 1, 'T' => -1],
            self::ElectricCurrent => ['I' => 1],
            self::Voltage => ['M' => 1, 'L' => 2, 'T' => -3, 'I' => -1],
            self::ElectricResistance => ['M' => 1, 'L' => 2, 'T' => -3, 'I' => -2],
            self::ElectricConductance => ['M' => -1, 'L' => -2, 'T' => 3, 'I' => 2],
            self::ElectricCharge => ['I' => 1, 'T' => 1],
            self::Capacitance => ['M' => -1, 'L' => -2, 'T' => 4, 'I' => 2],
            self::Inductance => ['M' => 1, 'L' => 2, 'T' => -2, 'I' => -2],
            self::MagneticFlux => ['M' => 1, 'L' => 2, 'T' => -2, 'I' => -1],
            self::MagneticFluxDensity => ['M' => 1, 'T' => -2, 'I' => -1],
            self::AmountOfSubstance => ['N' => 1],
            self::MolarConcentration => ['N' => 1, 'L' => -3],
            self::CatalyticActivity => ['N' => 1, 'T' => -1],
            self::LuminousIntensity => ['J' => 1],
            self::LuminousFlux => ['J' => 1, 'A' => 2],
            self::Illuminance => ['J' => 1, 'A' => 2, 'L' => -2],
            self::AbsorbedDose, self::EquivalentDose => ['L' => 2, 'T' => -2],
            self::Density => ['M' => 1, 'L' => -3],
            self::VolumetricFlowRate => ['L' => 3, 'T' => -1],
            self::FuelEconomy => ['L' => -2],
            self::Currency => ['¤' => 1],
            self::FantasyCurrency => ['G' => 1],
            self::Dimensionless => [],
            self::Derived => null,
        };

        return $exponents === null ? null : new DimensionVector($exponents);
    }

    /**
     * How many coherent SI units (products of base units) one of this
     * dimension's base unit is. Only fuel economy (km/L = 10⁶ m⁻²) isn't 1.
     */
    public function coherentFactor(): BigRational
    {
        return $this === self::FuelEconomy ? BigRational::of(1000000) : BigRational::one();
    }

    /**
     * The named dimension for a vector. Where several share a vector the
     * first declared wins (energy over torque, frequency over radioactivity).
     */
    public static function fromVector(DimensionVector $vector): self
    {
        /** @var array<string, self> $byVector */
        static $byVector = [];

        if ($byVector === []) {
            foreach (self::cases() as $dimension) {
                $key = $dimension->vector()?->key();

                if ($key !== null) {
                    $byVector[$key] ??= $dimension;
                }
            }
        }

        return $byVector[$vector->key()] ?? self::Derived;
    }
}
