<?php

declare(strict_types=1);

namespace Unitary\Scales;

use JsonSerializable;
use Stringable;

/**
 * A non-linear or tabulated sizing system that maps onto a physical quantity,
 * such as shoe sizes, ring sizes or wire gauges.
 */
interface Scale extends JsonSerializable, Stringable
{
    public function toString(): string;
}
