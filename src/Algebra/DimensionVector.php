<?php

declare(strict_types=1);

namespace Unitary\Algebra;

use Stringable;

/**
 * A dimension expressed as powers of base dimensions, e.g. speed = L·T⁻¹.
 *
 * Bases: the seven SI base dimensions (L, M, T, I, Θ, N, J), plus angle (A),
 * information (B), currency (¤) and fantasy coinage (G) so that rad/s, bit/s
 * and $/h stay distinct from Hz, Hz and Hz, and gold pieces never become dollars.
 */
final readonly class DimensionVector implements Stringable
{
    public const BASES = ['L', 'M', 'T', 'I', 'Θ', 'N', 'J', 'A', 'B', '¤', 'G'];

    /** @var array<string, int> base => non-zero exponent, in BASES order */
    public array $exponents;

    /**
     * @param  array<string, int>  $exponents
     */
    public function __construct(array $exponents = [])
    {
        $normalized = [];

        foreach (self::BASES as $base) {
            if (($exponents[$base] ?? 0) !== 0) {
                $normalized[$base] = $exponents[$base];
            }
        }

        $this->exponents = $normalized;
    }

    public static function none(): self
    {
        return new self();
    }

    public function multipliedBy(self $other): self
    {
        $exponents = $this->exponents;

        foreach ($other->exponents as $base => $exponent) {
            $exponents[$base] = ($exponents[$base] ?? 0) + $exponent;
        }

        return new self($exponents);
    }

    public function dividedBy(self $other): self
    {
        return $this->multipliedBy($other->power(-1));
    }

    public function power(int $exponent): self
    {
        return new self(array_map(fn (int $e) => $e * $exponent, $this->exponents));
    }

    public function equals(self $other): bool
    {
        return $this->exponents === $other->exponents;
    }

    public function isDimensionless(): bool
    {
        return $this->exponents === [];
    }

    public function key(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        if ($this->exponents === []) {
            return '1';
        }

        $parts = [];

        foreach ($this->exponents as $base => $exponent) {
            $parts[] = $base.($exponent === 1 ? '' : Superscript::of($exponent));
        }

        return implode('·', $parts);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
