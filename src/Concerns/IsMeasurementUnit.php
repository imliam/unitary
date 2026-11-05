<?php

declare(strict_types=1);

namespace Unitary\Concerns;

use Brick\Math\BigRational;
use Unitary\Prefix;
use Unitary\PrefixedUnit;
use Unitary\UnitDefinition;

/**
 * Shared behavior for the backed unit enums: the backing value is the symbol,
 * and each enum describes its cases via define().
 *
 * @phpstan-require-implements \Unitary\MeasurementUnit
 */
trait IsMeasurementUnit
{
    abstract protected function define(): UnitDefinition;

    public function definition(): UnitDefinition
    {
        /** @var array<string, UnitDefinition> $definitions */
        static $definitions = [];

        return $definitions[$this->name] ??= $this->define();
    }

    public function symbol(): string
    {
        return $this->value;
    }

    public function name(): string
    {
        return $this->definition()->name;
    }

    public function aliases(): array
    {
        $definition = $this->definition();

        return array_values(array_unique([...$definition->aliases, $definition->name, ...$definition->pluralForms()]));
    }

    public function toBase(BigRational $value): BigRational
    {
        return $this->definition()->toBase($value);
    }

    public function fromBase(BigRational $value): BigRational
    {
        return $this->definition()->fromBase($value);
    }

    public function withPrefix(Prefix $prefix): PrefixedUnit
    {
        return new PrefixedUnit($prefix, $this);
    }
}
