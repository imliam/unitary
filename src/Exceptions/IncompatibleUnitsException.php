<?php

declare(strict_types=1);

namespace Unitary\Exceptions;

use InvalidArgumentException;
use Unitary\Unit;

final class IncompatibleUnitsException extends InvalidArgumentException implements UnitaryException
{
    public static function between(Unit $from, Unit $to): self
    {
        return new self(sprintf(
            'Cannot convert %s (%s) to %s (%s)',
            $from->symbol(),
            $from->dimension()->label(),
            $to->symbol(),
            $to->dimension()->label(),
        ));
    }
}
