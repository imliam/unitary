<?php

declare(strict_types=1);

namespace Unitary\Calc;

use Brick\Math\BigRational;
use Unitary\Substances\Substance;

/**
 * @internal
 */
final readonly class Token
{
    public const NUMBER = 'number';

    public const OPERATOR = 'operator';

    public const WORD = 'word';

    public function __construct(
        public string $type,
        public string $text,
        public ?BigRational $value = null,
        public ?string $unit = null,
        public ?int $significantFigures = null,
        public ?Substance $substance = null,
    ) {
    }

    public function is(string $type, ?string $text = null): bool
    {
        return $this->type === $type && ($text === null || $this->text === $text);
    }
}
