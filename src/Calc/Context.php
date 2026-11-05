<?php

declare(strict_types=1);

namespace Unitary\Calc;

use Unitary\Quantity;
use Unitary\Unit;

/**
 * The state an expression can refer to: variables, `prev` and `sum`.
 *
 * @internal
 */
final readonly class Context
{
    /**
     * @param  array<string, Quantity<Unit>>  $variables
     * @param  Quantity<Unit>|null  $previous
     * @param  list<Quantity<Unit>>  $block
     */
    public function __construct(
        public array $variables = [],
        public ?Quantity $previous = null,
        public array $block = [],
    ) {
    }
}
