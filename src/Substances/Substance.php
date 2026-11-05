<?php

declare(strict_types=1);

namespace Unitary\Substances;

use Unitary\Dimension;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\Unitary;

/**
 * A material with the physical properties needed to convert between volume,
 * mass, amount of substance, energy and combustion CO₂. Each property is a ratio quantity kept in
 * the source's own units (120 g/cup, 33.705 kWh/gal), so conversions stay exact.
 */
final readonly class Substance
{
    /**
     * @param  list<string>  $aliases
     * @param  Quantity<Unit>|null  $density  mass per volume
     * @param  Quantity<Unit>|null  $energy  energy per volume or per mass
     * @param  Quantity<Unit>|null  $co2  CO₂ mass per volume or per mass
     * @param  array<string, string>  $sources  property => citation
     * @param  Quantity<Unit>|null  $molarMass  mass per amount of substance
     */
    public function __construct(
        public string $name,
        public array $aliases = [],
        public ?Quantity $density = null,
        public ?Quantity $energy = null,
        public ?string $energyBasis = null,
        public ?Quantity $co2 = null,
        public array $sources = [],
        public ?Quantity $molarMass = null,
    ) {
    }

    /**
     * Builds a substance from text ratios: ['density' => ['120 g', '1 cup'], 'energy' => ['33.705 kWh', '1 gal'], ...].
     *
     * @param  list<string>  $aliases
     * @param  array{string, string}|null  $density
     * @param  array{string, string}|null  $energy
     * @param  array{string, string}|null  $co2
     * @param  array<string, string>  $sources
     * @param  array{string, string}|null  $molarMass
     */
    public static function define(
        string $name,
        array $aliases = [],
        ?array $density = null,
        ?array $energy = null,
        ?string $energyBasis = null,
        ?array $co2 = null,
        array $sources = [],
        ?array $molarMass = null,
    ): self {
        return new self(
            $name,
            $aliases,
            $density === null ? null : self::ratio(...$density),
            $energy === null ? null : self::ratio(...$energy),
            $energyBasis,
            $co2 === null ? null : self::ratio(...$co2),
            $sources,
            $molarMass === null ? null : self::ratio(...$molarMass),
        );
    }

    /**
     * @return array<string, string>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    /** Whether the energy figure is quoted per unit of volume (true) or mass (false). */
    public function energyIsPerVolume(): ?bool
    {
        return $this->energy === null ? null : self::isPerVolume($this->energy);
    }

    /** Whether the CO₂ figure is quoted per unit of volume (true) or mass (false). */
    public function co2IsPerVolume(): ?bool
    {
        return $this->co2 === null ? null : self::isPerVolume($this->co2);
    }

    /**
     * @param  Quantity<Unit>  $ratio
     */
    private static function isPerVolume(Quantity $ratio): bool
    {
        $converter = Unitary::converter();
        $perLitre = $ratio->multipliedBy($converter->quantity(1, 'L'));

        return in_array($perLitre->dimension(), [Dimension::Mass, Dimension::Energy], true);
    }

    /**
     * @return Quantity<Unit>
     */
    private static function ratio(string $amount, string $per): Quantity
    {
        $converter = Unitary::converter();

        return $converter->parseQuantity($amount)->dividedBy($converter->parseQuantity($per));
    }
}
