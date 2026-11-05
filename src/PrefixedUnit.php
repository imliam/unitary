<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigRational;
use InvalidArgumentException;

/**
 * A prefix applied to a prefixable unit, e.g. gigameter or kibibyte.
 * Common combinations (km, mg, kWh...) also exist as enum cases.
 */
final readonly class PrefixedUnit implements MeasurementUnit
{
    private UnitDefinition $definition;

    public function __construct(public Prefix $prefix, public MeasurementUnit $unit)
    {
        $base = $unit->definition();

        if ($prefix->isBinary() ? ! $base->binaryPrefixes : ! $base->siPrefixes) {
            throw new InvalidArgumentException(sprintf('The %s prefix cannot be applied to %s', $prefix->name(), $base->name));
        }

        $this->definition = UnitDefinition::of(
            $prefix->name().$base->name,
            $base->factor->multipliedBy($prefix->factor()),
            $base->offset,
        )->plural(...array_map(fn (string $plural) => $prefix->name().$plural, $base->pluralForms()));
    }

    public function dimension(): Dimension
    {
        return $this->unit->dimension();
    }

    public function symbol(): string
    {
        return $this->prefix->symbol().$this->unit->symbol();
    }

    public function name(): string
    {
        return $this->definition->name;
    }

    public function aliases(): array
    {
        return [];
    }

    public function definition(): UnitDefinition
    {
        return $this->definition;
    }

    public function toBase(BigRational $value): BigRational
    {
        return $this->definition->toBase($value);
    }

    public function fromBase(BigRational $value): BigRational
    {
        return $this->definition->fromBase($value);
    }
}
