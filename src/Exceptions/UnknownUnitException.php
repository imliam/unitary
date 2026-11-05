<?php

declare(strict_types=1);

namespace Unitary\Exceptions;

use InvalidArgumentException;

final class UnknownUnitException extends InvalidArgumentException implements UnitaryException
{
    public static function for(string $unit): self
    {
        return new self(sprintf('Unknown unit "%s"', $unit));
    }
}
