<?php

declare(strict_types=1);

namespace Unitary\Exceptions;

use InvalidArgumentException;

final class ParseException extends InvalidArgumentException implements UnitaryException
{
    public static function cannotParse(string $input, string $what = 'value', ?string $reason = null): self
    {
        $article = preg_match('/^[aeiou]/i', $what) ? 'an' : 'a';
        $message = sprintf('Unable to parse "%s" as %s %s', $input, $article, $what);

        return new self($reason === null ? $message : "{$message}: {$reason}");
    }
}
