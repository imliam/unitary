<?php

declare(strict_types=1);

namespace Unitary\Currency;

use Brick\Math\BigRational;
use DateTimeImmutable;

/**
 * One unit of $from is worth $rate units of $to.
 */
final readonly class ExchangeRate
{
    public function __construct(
        public Currency $from,
        public Currency $to,
        public BigRational $rate,
        public ?DateTimeImmutable $date = null,
        public string $source = '',
    ) {
    }

    public function inverse(): self
    {
        return new self($this->to, $this->from, $this->rate->reciprocal(), $this->date, $this->source);
    }
}
