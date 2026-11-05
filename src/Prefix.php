<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigRational;
use Unitary\Support\Exact;

/**
 * SI decimal prefixes (including the 2022 ronna/quetta/ronto/quecto additions)
 * and IEC binary prefixes.
 */
enum Prefix: string
{
    case Quetta = 'Q';
    case Ronna = 'R';
    case Yotta = 'Y';
    case Zetta = 'Z';
    case Exa = 'E';
    case Peta = 'P';
    case Tera = 'T';
    case Giga = 'G';
    case Mega = 'M';
    case Kilo = 'k';
    case Hecto = 'h';
    case Deca = 'da';
    case Deci = 'd';
    case Centi = 'c';
    case Milli = 'm';
    case Micro = 'µ';
    case Nano = 'n';
    case Pico = 'p';
    case Femto = 'f';
    case Atto = 'a';
    case Zepto = 'z';
    case Yocto = 'y';
    case Ronto = 'r';
    case Quecto = 'q';

    case Kibi = 'Ki';
    case Mebi = 'Mi';
    case Gibi = 'Gi';
    case Tebi = 'Ti';
    case Pebi = 'Pi';
    case Exbi = 'Ei';
    case Zebi = 'Zi';
    case Yobi = 'Yi';

    public function symbol(): string
    {
        return $this->value;
    }

    public function name(): string
    {
        return strtolower($this->name);
    }

    public function isBinary(): bool
    {
        return str_ends_with($this->value, 'i') && strlen($this->value) === 2;
    }

    public function factor(): BigRational
    {
        return match ($this) {
            self::Kibi => BigRational::of(2)->power(10),
            self::Mebi => BigRational::of(2)->power(20),
            self::Gibi => BigRational::of(2)->power(30),
            self::Tebi => BigRational::of(2)->power(40),
            self::Pebi => BigRational::of(2)->power(50),
            self::Exbi => BigRational::of(2)->power(60),
            self::Zebi => BigRational::of(2)->power(70),
            self::Yobi => BigRational::of(2)->power(80),
            default => Exact::power(BigRational::ten(), $this->exponent()),
        };
    }

    /** The power of ten for SI prefixes (0 for binary prefixes). */
    public function exponent(): int
    {
        return match ($this) {
            self::Quetta => 30, self::Ronna => 27, self::Yotta => 24, self::Zetta => 21,
            self::Exa => 18, self::Peta => 15, self::Tera => 12, self::Giga => 9,
            self::Mega => 6, self::Kilo => 3, self::Hecto => 2, self::Deca => 1,
            self::Deci => -1, self::Centi => -2, self::Milli => -3, self::Micro => -6,
            self::Nano => -9, self::Pico => -12, self::Femto => -15, self::Atto => -18,
            self::Zepto => -21, self::Yocto => -24, self::Ronto => -27, self::Quecto => -30,
            default => 0,
        };
    }

    /**
     * Symbol spellings accepted when parsing ("u" and the Greek mu for micro, "K" for kibi...).
     *
     * @return list<string>
     */
    public function symbolVariants(): array
    {
        return match ($this) {
            self::Micro => ['µ', 'μ', 'u'],
            self::Kibi => ['Ki', 'ki'],
            default => [$this->value],
        };
    }

    /** @return list<string> */
    public function nameVariants(): array
    {
        return match ($this) {
            self::Deca => ['deca', 'deka'],
            default => [$this->name()],
        };
    }
}
