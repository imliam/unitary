<?php

declare(strict_types=1);

namespace Unitary\Substances;

use Unitary\Dimension;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\UnitRegistry;

/**
 * Converts amounts of a substance between volume, mass, moles, energy and combustion CO₂:
 * "1 cup of flour in g", "1 gal of gasoline in kWh", "1 gal of diesel in kg CO2",
 * "5.5 mmol/L of glucose in mg/dL".
 */
final class SubstanceConverter
{
    /** "kg CO2", "kg CO₂", "lb of CO2", "tonnes CO2e", "kg of carbon dioxide". */
    private const CO2_MARKER = '/\s*(?:of\s+)?(?:CO[2₂]e?|carbon dioxide)\s*$/iu';

    /**
     * @param  Quantity<Unit>  $amount  a volume, mass or energy
     * @return Quantity<Unit>
     *
     * @throws SubstanceException when the substance lacks a property the conversion needs
     */
    public static function convert(Quantity $amount, Substance $substance, Unit|string $target, ?UnitRegistry $units = null): Quantity
    {
        $units ??= UnitRegistry::default();
        $co2 = false;

        if (is_string($target) && preg_match(self::CO2_MARKER, $target) === 1) {
            $co2 = true;
            $target = trim((string) preg_replace(self::CO2_MARKER, '', $target));
        }

        $target = $units->resolve($target, $co2 ? Dimension::Mass : null);
        $from = $amount->dimension();

        if ($co2) {
            if ($target->dimension() !== Dimension::Mass) {
                throw SubstanceException::cannotConvert($substance, $from->label(), $target->symbol().' of CO₂');
            }

            $ratio = $substance->co2 ?? throw SubstanceException::missing($substance, 'CO₂ emission factor');
            $basis = $substance->co2IsPerVolume() === true ? self::volume($amount, $substance) : self::mass($amount, $substance);

            return $basis->multipliedBy($ratio)->to($target);
        }

        return match ($target->dimension()) {
            Dimension::Mass => self::mass($amount, $substance)->to($target),
            Dimension::AmountOfSubstance => self::moles($amount, $substance)->to($target),
            Dimension::Density => self::massConcentration($amount, $substance)->to($target),
            Dimension::MolarConcentration => self::molarConcentration($amount, $substance)->to($target),
            Dimension::Volume => self::volume($amount, $substance)->to($target),
            Dimension::Energy => self::energy($amount, $substance)->to($target),
            default => throw SubstanceException::cannotConvert($substance, $from->label(), $target->dimension()->label()),
        };
    }

    /**
     * Splits "1 cup of flour", "2 cups flour" or "a gallon of petrol" into the
     * amount text and the substance. Only matches when the trailing words name
     * a known substance; the amount still has to be parsed as a quantity.
     *
     * @return array{amount: string, substance: Substance}|null
     */
    public static function tryParse(string $source): ?array
    {
        $words = preg_split('/\s+/u', trim($source), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Prefer the longest substance name: "olive oil" over "oil".
        for ($i = 1, $count = count($words); $i < $count; $i++) {
            $substance = Substances::find(implode(' ', array_slice($words, $i)));

            if ($substance === null) {
                continue;
            }

            $amount = array_slice($words, 0, $i);

            if (strtolower((string) end($amount)) === 'of') {
                array_pop($amount);
            }

            if ($amount !== []) {
                return ['amount' => implode(' ', $amount), 'substance' => $substance];
            }
        }

        return null;
    }

    /**
     * @param  Quantity<Unit>  $amount
     * @return Quantity<Unit>
     */
    private static function mass(Quantity $amount, Substance $substance): Quantity
    {
        return match ($amount->dimension()) {
            Dimension::Mass => $amount,
            Dimension::AmountOfSubstance => $amount->multipliedBy(self::molarMass($substance)),
            Dimension::Volume => $amount->multipliedBy($substance->density ?? throw SubstanceException::missing($substance, 'density')),
            Dimension::Energy => $substance->energyIsPerVolume() === false
                ? $amount->dividedBy(self::energyRatio($substance))
                : self::volume($amount, $substance)->multipliedBy($substance->density ?? throw SubstanceException::missing($substance, 'density')),
            default => throw SubstanceException::cannotConvert($substance, $amount->dimension()->label(), 'mass'),
        };
    }

    /**
     * @param  Quantity<Unit>  $amount
     * @return Quantity<Unit>
     */
    private static function volume(Quantity $amount, Substance $substance): Quantity
    {
        return match ($amount->dimension()) {
            Dimension::Volume => $amount,
            Dimension::Mass => $amount->dividedBy($substance->density ?? throw SubstanceException::missing($substance, 'density')),
            Dimension::Energy => $substance->energyIsPerVolume() === true
                ? $amount->dividedBy(self::energyRatio($substance))
                : self::mass($amount, $substance)->dividedBy($substance->density ?? throw SubstanceException::missing($substance, 'density')),
            default => throw SubstanceException::cannotConvert($substance, $amount->dimension()->label(), 'volume'),
        };
    }

    /**
     * @param  Quantity<Unit>  $amount
     * @return Quantity<Unit>
     */
    private static function energy(Quantity $amount, Substance $substance): Quantity
    {
        if ($amount->dimension() === Dimension::Energy) {
            return $amount;
        }

        $ratio = self::energyRatio($substance);

        return $substance->energyIsPerVolume() === true
            ? self::volume($amount, $substance)->multipliedBy($ratio)
            : self::mass($amount, $substance)->multipliedBy($ratio);
    }

    /**
     * @param  Quantity<Unit>  $amount
     * @return Quantity<Unit>
     */
    private static function moles(Quantity $amount, Substance $substance): Quantity
    {
        return $amount->dimension() === Dimension::AmountOfSubstance
            ? $amount
            : self::mass($amount, $substance)->dividedBy(self::molarMass($substance));
    }

    /**
     * Mass per volume, from a molar concentration: 5.5 mmol/L of glucose → 99.09 mg/dL.
     *
     * @param  Quantity<Unit>  $amount
     * @return Quantity<Unit>
     */
    private static function massConcentration(Quantity $amount, Substance $substance): Quantity
    {
        return match ($amount->dimension()) {
            Dimension::Density => $amount,
            Dimension::MolarConcentration => $amount->multipliedBy(self::molarMass($substance)),
            default => throw SubstanceException::cannotConvert($substance, $amount->dimension()->label(), 'density'),
        };
    }

    /**
     * @param  Quantity<Unit>  $amount
     * @return Quantity<Unit>
     */
    private static function molarConcentration(Quantity $amount, Substance $substance): Quantity
    {
        return match ($amount->dimension()) {
            Dimension::MolarConcentration => $amount,
            Dimension::Density => $amount->dividedBy(self::molarMass($substance)),
            default => throw SubstanceException::cannotConvert($substance, $amount->dimension()->label(), 'molar concentration'),
        };
    }

    /**
     * @return Quantity<Unit>
     */
    private static function molarMass(Substance $substance): Quantity
    {
        return $substance->molarMass ?? throw SubstanceException::missing($substance, 'molar mass');
    }

    /**
     * @return Quantity<Unit>
     */
    private static function energyRatio(Substance $substance): Quantity
    {
        return $substance->energy ?? throw SubstanceException::missing($substance, 'energy content');
    }
}
