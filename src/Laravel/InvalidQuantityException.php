<?php

declare(strict_types=1);

namespace Unitary\Laravel;

use InvalidArgumentException;
use Unitary\Dimension;
use Unitary\Exceptions\UnitaryException;

final class InvalidQuantityException extends InvalidArgumentException implements UnitaryException
{
    public static function wrongDimension(string $attribute, Dimension $expected, Dimension $actual): self
    {
        return new self(sprintf('The %s attribute expects a %s quantity, %s given', $attribute, $expected->label(), $actual->label()));
    }

    public static function unitRequired(string $attribute): self
    {
        return new self(sprintf('The %s attribute needs a unit, e.g. "5 kg"; a bare number is ambiguous', $attribute));
    }

    public static function unsupported(string $attribute, mixed $value): self
    {
        return new self(sprintf('Cannot store a %s in the %s quantity attribute', get_debug_type($value), $attribute));
    }
}
