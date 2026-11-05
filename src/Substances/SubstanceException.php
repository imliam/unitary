<?php

declare(strict_types=1);

namespace Unitary\Substances;

use InvalidArgumentException;
use Unitary\Exceptions\UnitaryException;

final class SubstanceException extends InvalidArgumentException implements UnitaryException
{
    public static function missing(Substance $substance, string $property): self
    {
        return new self(sprintf('No %s is known for %s', $property, $substance->name));
    }

    public static function cannotConvert(Substance $substance, string $from, string $to): self
    {
        return new self(sprintf('Cannot convert %s of %s to %s', $from, $substance->name, $to));
    }
}
