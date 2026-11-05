<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Unitary\Support\Exact;
use Unitary\Support\Transcendental;

/**
 * How a unit relates to its dimension's base unit.
 *
 * Linear units:      base = value × factor + offset
 * Reciprocal units:  base = factor ÷ value                         (e.g. liters per 100 km)
 * Logarithmic units: base = factor × logBase^(value ÷ logDivisor)   (e.g. dBm: 1 mW × 10^(x/10))
 */
final class UnitDefinition
{
    public readonly BigRational $factor;

    public readonly BigRational $offset;

    public bool $reciprocal = false;

    /** Whether SI prefixes (kilo, milli...) may be applied when parsing. */
    public bool $siPrefixes = false;

    /** Whether binary IEC prefixes (kibi, mebi...) may be applied when parsing. */
    public bool $binaryPrefixes = false;

    /** For logarithmic units: the base of the logarithm ("10", "2" or "e"). */
    public ?string $logBase = null;

    public BigRational $logDivisor;

    /** @var list<string> */
    public array $aliases = [];

    /** @var list<string> */
    public array $plurals = [];

    public function __construct(
        public readonly string $name,
        BigNumber|int|string $factor = 1,
        BigNumber|int|string $offset = 0,
    ) {
        $this->factor = Exact::of($factor);
        $this->offset = Exact::of($offset);
        $this->logDivisor = BigRational::one();
    }

    public static function of(string $name, BigNumber|int|string $factor = 1, BigNumber|int|string $offset = 0): self
    {
        return new self($name, $factor, $offset);
    }

    public function aliases(string ...$aliases): self
    {
        $this->aliases = [...$this->aliases, ...array_values($aliases)];

        return $this;
    }

    /** Overrides the automatically generated plural forms of the name. */
    public function plural(string ...$plurals): self
    {
        $this->plurals = array_values($plurals);

        return $this;
    }

    public function prefixable(bool $binary = false): self
    {
        $this->siPrefixes = true;
        $this->binaryPrefixes = $binary;

        return $this;
    }

    /**
     * A logarithmic scale: base = factor × logBase^(value ÷ divisor).
     * Decibels of power use base 10, divisor 10; of field quantities, divisor 20.
     */
    public function logarithmic(string $logBase = '10', BigNumber|int|string $divisor = 10): self
    {
        $this->logBase = $logBase;
        $this->logDivisor = Exact::of($divisor);

        return $this;
    }

    /** Whether the unit is a plain multiple of its base (no offset, reciprocal or logarithm). */
    public function isLinear(): bool
    {
        return $this->offset->isZero() && ! $this->reciprocal && $this->logBase === null;
    }

    public function reciprocal(): self
    {
        $this->reciprocal = true;

        return $this;
    }

    public function toBase(BigRational $value): BigRational
    {
        if ($this->logBase !== null) {
            return $this->factor->multipliedBy(Transcendental::power($this->logBase, $value->dividedBy($this->logDivisor)));
        }

        if ($this->reciprocal) {
            return $this->factor->dividedBy($value);
        }

        return $value->multipliedBy($this->factor)->plus($this->offset);
    }

    public function fromBase(BigRational $value): BigRational
    {
        if ($this->logBase !== null) {
            return Transcendental::log($this->logBase, $value->dividedBy($this->factor))->multipliedBy($this->logDivisor);
        }

        if ($this->reciprocal) {
            return $this->factor->dividedBy($value);
        }

        return $value->minus($this->offset)->dividedBy($this->factor);
    }

    /**
     * @return list<string>
     */
    public function pluralForms(): array
    {
        return $this->plurals !== [] ? $this->plurals : [self::pluralise($this->name)];
    }

    public static function pluralise(string $name): string
    {
        // Pluralise the head noun of "x per y" and "x of y" names.
        foreach ([' per ', ' of ', ' force'] as $separator) {
            $position = strpos($name, $separator);

            if ($position !== false && $position > 0) {
                return self::pluralise(substr($name, 0, $position)).substr($name, $position);
            }
        }

        return match (true) {
            str_ends_with($name, 'foot') => substr($name, 0, -4).'feet',
            str_ends_with($name, 'tooth') => substr($name, 0, -5).'teeth',
            str_ends_with($name, 'hertz'), str_ends_with($name, 'lux'), str_ends_with($name, 'siemens'),
            str_ends_with($name, 'celsius'), str_ends_with($name, 'fahrenheit'), str_ends_with($name, 'rankine'),
            str_ends_with($name, 'réaumur'), str_ends_with($name, 'delisle'), str_ends_with($name, 'newton scale'),
            str_ends_with($name, 'rømer'), str_ends_with($name, 'kelvin'), str_ends_with($name, 'mach'), str_ends_with($name, 'yen') => $name,
            (bool) preg_match('/(s|x|z|ch|sh)$/', $name) => $name.'es',
            (bool) preg_match('/[^aeiou]y$/', $name) => substr($name, 0, -1).'ies',
            default => $name.'s',
        };
    }
}
