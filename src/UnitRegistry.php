<?php

declare(strict_types=1);

namespace Unitary;

use Unitary\Algebra\Superscript;
use Unitary\Currency\Currency;
use Unitary\Exceptions\UnknownUnitException;
use Unitary\Units;

/**
 * Resolves freeform unit text ("km", "Kilometres", "GBP", "£", "gigawatt hours")
 * to Unit instances.
 *
 * Lookup order, stopping at the first stage that produces matches:
 *  1. exact (case-sensitive) symbols and aliases
 *  2. SI/IEC prefix + symbol of a prefixable unit ("Gm", "KiB", "µs")
 *  3. case-insensitive symbols and aliases ("KM", "gbp")
 *  4. prefix + name of a prefixable unit ("gigametres", "kibibytes")
 *  5. a compound of known units ("kg⋅m/s²", "$/h", "W per m²", "/week")
 *
 * Within a stage, units registered first win; pass a preferred dimension to
 * resolve() to disambiguate ("m" is meter by default, minute when time is preferred).
 */
final class UnitRegistry
{
    /** @var list<class-string<Unit&\BackedEnum>> */
    public const DEFAULT_ENUMS = [
        Units\Length::class,
        Units\Mass::class,
        Units\Time::class,
        Units\Temperature::class,
        Units\TemperatureDifference::class,
        Units\Volume::class,
        Units\Area::class,
        Units\Speed::class,
        Units\Acceleration::class,
        Units\Force::class,
        Units\Pressure::class,
        Units\Energy::class,
        Units\Power::class,
        Units\Torque::class,
        Units\Frequency::class,
        Units\Angle::class,
        Units\SolidAngle::class,
        Units\DigitalStorage::class,
        Units\DataRate::class,
        Units\ElectricCurrent::class,
        Units\Voltage::class,
        Units\ElectricResistance::class,
        Units\ElectricConductance::class,
        Units\ElectricCharge::class,
        Units\Capacitance::class,
        Units\Inductance::class,
        Units\MagneticFlux::class,
        Units\MagneticFluxDensity::class,
        Units\AmountOfSubstance::class,
        Units\MolarConcentration::class,
        Units\CatalyticActivity::class,
        Units\LuminousIntensity::class,
        Units\LuminousFlux::class,
        Units\Illuminance::class,
        Units\Radioactivity::class,
        Units\AbsorbedDose::class,
        Units\EquivalentDose::class,
        Units\Density::class,
        Units\VolumetricFlowRate::class,
        Units\FuelEconomy::class,
        Units\Ratio::class,
        Currency::class,
        // Novelty units rank below everything real when text is ambiguous.
        Units\Novelty\NoveltyLength::class,
        Units\Novelty\NoveltyArea::class,
        Units\Novelty\NoveltyVolume::class,
        Units\Novelty\NoveltyMass::class,
        Units\Novelty\NoveltyTime::class,
        Units\Novelty\NoveltySpeed::class,
        Units\Novelty\DndCurrency::class,
    ];

    private static ?self $default = null;

    /** @var list<Unit> */
    private array $units = [];

    /** @var array<string, list<Unit>> */
    private array $exact = [];

    /** @var array<string, list<Unit>> */
    private array $insensitive = [];

    /** @var array<string, list<MeasurementUnit>> prefixable units keyed by symbol */
    private array $prefixableSymbols = [];

    /** @var array<string, list<MeasurementUnit>> prefixable units keyed by lower-case name/plural */
    private array $prefixableNames = [];

    /** @var array<string, list<MeasurementUnit>> linear units keyed by dimension, for canonicalizing compounds */
    private array $byDimension = [];

    /**
     * @param  list<class-string<Unit&\BackedEnum>>  $enums
     */
    public function __construct(array $enums = self::DEFAULT_ENUMS)
    {
        foreach ($enums as $enum) {
            $this->register(...$enum::cases());
        }
    }

    /** A shared registry of every built-in unit. */
    public static function default(): self
    {
        return self::$default ??= new self();
    }

    /**
     * Registers additional units (for example your own enum cases). Later
     * registrations have lower priority for ambiguous text.
     */
    public function register(Unit ...$units): self
    {
        foreach ($units as $unit) {
            $this->units[] = $unit;

            if ($unit instanceof MeasurementUnit && $unit->definition()->isLinear()) {
                $this->byDimension[$unit->dimension()->value][] = $unit;
            }

            foreach ($this->spellings([$unit->symbol(), ...$unit->aliases()]) as $text) {
                $this->add($this->exact, $text, $unit);
                $this->add($this->insensitive, mb_strtolower($text), $unit);
            }

            if (! $unit instanceof MeasurementUnit || ! $unit->definition()->siPrefixes) {
                continue;
            }

            $this->add($this->prefixableSymbols, $unit->symbol(), $unit);

            // Word-like aliases can be prefixed too: "amp" => "milliamp", "watt hours" => "gigawatt hours".
            $words = array_filter($unit->aliases(), fn (string $alias) => (bool) preg_match('/^\p{L}[\p{L} -]{2,}$/u', $alias));

            foreach ($this->spellings([$unit->name(), ...$unit->definition()->pluralForms(), ...array_values($words)]) as $name) {
                $this->add($this->prefixableNames, mb_strtolower($name), $unit);
            }
        }

        return $this;
    }

    /**
     * @throws UnknownUnitException
     */
    public function resolve(Unit|string $unit, ?Dimension $prefer = null): Unit
    {
        return $this->tryResolve($unit, $prefer) ?? throw UnknownUnitException::for((string) (is_string($unit) ? $unit : $unit->symbol()));
    }

    public function tryResolve(Unit|string $unit, ?Dimension $prefer = null): ?Unit
    {
        if ($unit instanceof Unit) {
            return $unit;
        }

        // With a preferred dimension, look past the first matching stage: "c" is the speed of
        // light, but when converting temperatures it should be read case-insensitively as °C.
        if ($prefer !== null) {
            foreach ($this->candidates($unit, exhaustive: true) as $candidate) {
                if ($candidate->dimension() === $prefer) {
                    return $candidate;
                }
            }
        }

        return $this->candidates($unit)[0] ?? null;
    }

    /**
     * Every unit the text could refer to, most likely first. Normally only the
     * first lookup stage with matches counts; exhaustive includes every stage.
     *
     * @return list<Unit>
     */
    public function candidates(string $text, bool $compounds = true, bool $exhaustive = false): array
    {
        $text = self::normalize($text);

        if ($text === '') {
            return [];
        }

        $variants = array_values(array_unique([$text, rtrim($text, '.')]));
        $candidates = [];

        foreach ($variants as $variant) {
            $candidates = [...$candidates, ...($this->exact[$variant] ?? [])];
        }

        if ($candidates === [] || $exhaustive) {
            foreach ($variants as $variant) {
                $candidates = [...$candidates, ...$this->prefixed($variant, $this->prefixableSymbols, symbols: true)];
            }
        }

        if ($candidates === [] || $exhaustive) {
            foreach ($variants as $variant) {
                $candidates = [...$candidates, ...($this->insensitive[mb_strtolower($variant)] ?? [])];
            }
        }

        if ($candidates === [] || $exhaustive) {
            foreach ($variants as $variant) {
                $candidates = [...$candidates, ...$this->prefixed(mb_strtolower($variant), $this->prefixableNames, symbols: false)];
            }
        }

        if ($candidates === [] && $compounds && ($compound = $this->parseCompound($text)) !== null) {
            $candidates = [$this->canonical($compound)];
        }

        return $this->unique($candidates);
    }

    /**
     * Swaps a compound for an equivalent named unit where there is one:
     * km⋅km → km², J/s → W, kg⋅m/s² → N, m/m → 1.
     */
    public function canonical(Unit $unit): Unit
    {
        if (! $unit instanceof CompoundUnit) {
            return $unit;
        }

        if ($unit->isEmpty()) {
            return Units\Ratio::One;
        }

        if (count($unit->factors) === 1 && $unit->factors[0]['power'] === 1) {
            return $unit->factors[0]['unit'];
        }

        $factor = $unit->coherentFactor();

        foreach ($this->exact[self::normalize($unit->symbol())] ?? [] as $named) {
            if (! $named instanceof CompoundUnit && CompoundUnit::vectorOf($named)->equals($unit->vector())
                && CompoundUnit::coherentFactorOf($named)->isEqualTo($factor)) {
                return $named;
            }
        }

        // A coherent combination of SI units becomes the dimension's coherent unit (J/s → W).
        if ($unit->currency() === null && $unit->dimension() !== Dimension::Derived && $factor->isEqualTo(1)) {
            foreach ($this->byDimension[$unit->dimension()->value] ?? [] as $named) {
                if (CompoundUnit::coherentFactorOf($named)->isEqualTo(1)) {
                    return $named;
                }
            }
        }

        return $unit;
    }

    /**
     * The coherent SI unit with the same dimension: km/h → m/s, kWh → J,
     * m⋅ft → m², $/kWh → USD/J. Currencies are left alone.
     */
    public function coherent(Unit $unit): Unit
    {
        if ($unit instanceof Currency) {
            return $unit;
        }

        if (! $unit instanceof CompoundUnit) {
            foreach ($this->byDimension[$unit->dimension()->value] ?? [] as $named) {
                if (CompoundUnit::coherentFactorOf($named)->isEqualTo(1)) {
                    return $named;
                }
            }

            return $unit;
        }

        $canonical = $this->canonical(new CompoundUnit(array_map(
            fn (array $factor) => ['unit' => $this->coherent($factor['unit']), 'power' => $factor['power']],
            $unit->factors,
        )));

        return $canonical instanceof CompoundUnit ? $canonical : $this->coherent($canonical);
    }

    /**
     * Splits a named derived unit into its parts when its symbol spells them
     * out (km/h → km⋅h⁻¹, km² → km²), so they can cancel in arithmetic.
     */
    public function expand(Unit $unit): Unit
    {
        if ($unit instanceof CompoundUnit || ! preg_match('/[\/⋅]|'.Superscript::pattern().'/u', $unit->symbol())) {
            return $unit;
        }

        $compound = $this->parseCompound(self::normalize($unit->symbol()));

        return $compound !== null && CompoundUnit::coherentFactorOf($unit)->isEqualTo($compound->coherentFactor())
            && $compound->vector()->equals(CompoundUnit::vectorOf($unit)) ? $compound : $unit;
    }

    private function parseCompound(string $text): ?CompoundUnit
    {
        if (($perUnit = $this->parsePerAbbreviation($text)) !== null) {
            return $perUnit;
        }

        if (! preg_match('/[\/⋅*^ ]|'.Superscript::pattern().'|\p{L}[23]\b/u', $text)) {
            return null;
        }

        $groups = preg_split('/\s*\/\s*|\s+per\s+/iu', $text) ?: [];
        $factors = [];

        foreach ($groups as $index => $group) {
            $sign = $index === 0 ? 1 : -1;
            $group = trim($group, " ()");

            if ($group === '' || ($group === '1' && $sign === 1)) {
                continue;
            }

            // Space-separated factors ("kg m") must be exact symbols, so ordinary words never multiply.
            $spaced = (bool) preg_match('/\s/u', (string) preg_replace('/\s*[⋅*]\s*/u', '', $group));

            if ($spaced && preg_match('/(?:^|\s)(?:to|in|into|as)(?:\s|$)/iu', $group)) {
                return null;
            }

            foreach (preg_split('/\s*[⋅*]\s*|\s+/u', $group, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $factor = $this->compoundFactor($token, exact: $spaced);

                if ($factor === null) {
                    return null;
                }

                $factors[] = ['unit' => $factor['unit'], 'power' => $factor['power'] * $sign];
            }
        }

        $isCompound = count($factors) > 1 || count($groups) > 1 || ($factors[0]['power'] ?? 1) !== 1;

        try {
            return $factors !== [] && $isCompound ? new CompoundUnit($factors) : null;
        } catch (Exceptions\IncompatibleUnitsException) {
            return null;
        }
    }

    /**
     * "kmps", "kgph", "mLpmin": X per Y written with a "p", the way kph and mph are.
     * Both sides must be exact symbols, so ordinary words and real units never match.
     */
    private function parsePerAbbreviation(string $text): ?CompoundUnit
    {
        if (! preg_match('/^[\p{L}µ]+$/u', $text)) {
            return null;
        }

        for ($offset = 1, $length = strlen($text); $offset < $length - 1; $offset++) {
            if ($text[$offset] !== 'p') {
                continue;
            }

            $numerator = $this->symbolUnit(substr($text, 0, $offset));
            $denominator = $this->symbolUnit(substr($text, $offset + 1));

            if ($numerator !== null && $denominator !== null) {
                try {
                    return CompoundUnit::quotient($numerator, $denominator);
                } catch (Exceptions\IncompatibleUnitsException) {
                    return null;
                }
            }
        }

        return null;
    }

    /** A unit written as its exact (case-sensitive) symbol, optionally with an SI prefix. */
    private function symbolUnit(string $text): ?Unit
    {
        return $this->exact[$text][0] ?? $this->prefixed($text, $this->prefixableSymbols, symbols: true)[0] ?? null;
    }

    /**
     * @return array{unit: Unit, power: int}|null
     */
    private function compoundFactor(string $token, bool $exact = false): ?array
    {
        $unit = $exact ? ($this->exact[self::normalize($token)][0] ?? null) : ($this->candidates($token, compounds: false)[0] ?? null);

        if ($unit !== null) {
            return ['unit' => $unit, 'power' => 1];
        }

        if (! preg_match('/^(?<unit>.+?)(?:\^(?<caret>-?\d+)|(?<sup>'.Superscript::pattern().')|(?<=\p{L})(?<digit>[23]))$/u', $token, $matches)) {
            return null;
        }

        $unit = $exact ? ($this->exact[self::normalize($matches['unit'])][0] ?? null) : ($this->candidates($matches['unit'], compounds: false)[0] ?? null);
        $power = match (true) {
            ($matches['caret'] ?? '') !== '' => (int) $matches['caret'],
            ($matches['sup'] ?? '') !== '' => Superscript::parse($matches['sup']),
            default => (int) ($matches['digit'] ?? 1),
        };

        return $unit !== null && $power !== null && $power !== 0 ? ['unit' => $unit, 'power' => $power] : null;
    }

    /**
     * @return list<Unit>
     */
    public function all(?Dimension $dimension = null): array
    {
        if ($dimension === null) {
            return $this->units;
        }

        return array_values(array_filter($this->units, fn (Unit $unit) => $unit->dimension() === $dimension));
    }

    /**
     * Tidies whitespace and typographic variants so lookups are forgiving.
     */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}", '·', '•', '×', 'μ'], [' ', ' ', '⋅', '⋅', '⋅', 'µ'], $text);
        $text = (string) preg_replace('/\s+/u', ' ', trim($text));
        $text = (string) preg_replace('/° (?=\p{L})/u', '°', $text);

        return (string) preg_replace('/\s*([\/^⋅])\s*/u', '$1', $text);
    }

    /**
     * @param  array<string, list<MeasurementUnit>>  $index
     * @return list<Unit>
     */
    private function prefixed(string $text, array $index, bool $symbols): array
    {
        $matches = [];

        foreach (Prefix::cases() as $prefix) {
            foreach ($symbols ? $prefix->symbolVariants() : $prefix->nameVariants() as $spelling) {
                if (! str_starts_with($text, $spelling) || strlen($text) === strlen($spelling)) {
                    continue;
                }

                $rest = substr($text, strlen($spelling));

                foreach ($index[$rest] ?? [] as $unit) {
                    $definition = $unit->definition();

                    if ($prefix->isBinary() ? ! $definition->binaryPrefixes : ! $definition->siPrefixes) {
                        continue;
                    }

                    $matches[] = $this->canonicalPrefixed(new PrefixedUnit($prefix, $unit));
                }
            }
        }

        return $matches;
    }

    /** Swap a generated prefixed unit for the equivalent enum case, if one exists. */
    private function canonicalPrefixed(PrefixedUnit $unit): Unit
    {
        foreach ($this->exact[$unit->symbol()] ?? [] as $existing) {
            if ($existing->dimension() === $unit->dimension() && $existing instanceof MeasurementUnit
                && $existing->definition()->factor->isEqualTo($unit->definition()->factor)) {
                return $existing;
            }
        }

        return $unit;
    }

    /**
     * @param  list<string>  $texts
     * @return list<string>
     */
    private function spellings(array $texts): array
    {
        $spellings = [];

        foreach ($texts as $text) {
            $text = self::normalize($text);
            $spellings[] = $text;
            $spellings[] = str_replace(['meter', 'liter'], ['metre', 'litre'], $text);
        }

        return array_values(array_unique($spellings));
    }

    /**
     * @template T of Unit
     *
     * @param  array<string, list<T>>  $index
     * @param  T  $unit
     */
    private function add(array &$index, string $key, Unit $unit): void
    {
        if (! in_array($unit, $index[$key] ?? [], true)) {
            $index[$key][] = $unit;
        }
    }

    /**
     * @param  list<Unit>  $units
     * @return list<Unit>
     */
    private function unique(array $units): array
    {
        $unique = [];

        foreach ($units as $unit) {
            $unique[$unit->dimension()->value.':'.$unit->symbol()] ??= $unit;
        }

        return array_values($unique);
    }
}
