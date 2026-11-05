<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigRational;

/**
 * A unit with a fixed mathematical relationship to its dimension's base unit,
 * so conversions are exact and need no external data.
 */
interface MeasurementUnit extends Unit
{
    public function definition(): UnitDefinition;

    public function toBase(BigRational $value): BigRational;

    public function fromBase(BigRational $value): BigRational;
}
