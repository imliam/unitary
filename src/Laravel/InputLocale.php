<?php

declare(strict_types=1);

namespace Unitary\Laravel;

use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Unitary\Dimension;
use Unitary\Parsing\LocalizedNumbers;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\Unitary;

/**
 * Reads what people type into forms the way their locale writes it: "1,5 kg"
 * and "5 Fuß 10 Zoll" in German. Used for request input and validation, not
 * for values set in code, like casts, which are read the English way.
 *
 * @internal
 */
final class InputLocale
{
    /**
     * The configured locale for input, else the app's.
     */
    public static function current(): string
    {
        $locale = app(ConfigRepository::class)->get('unitary.locale');

        return is_string($locale) && $locale !== '' ? $locale : app()->getLocale();
    }

    /**
     * A number on its own, written the locale's way, like "1,5" in German.
     */
    public static function number(string $value, ?string $locale = null): ?BigRational
    {
        return NumberParser::tryParse(LocalizedNumbers::normalize($value, $locale ?? self::current()));
    }

    /**
     * Reads input as a quantity: a number on its own in $unit, or a number and a unit.
     *
     * @return \Unitary\Quantity<\Unitary\Unit>|null  null for a number on its own without a unit
     *
     * @throws \Unitary\Exceptions\ParseException
     */
    public static function quantity(BigNumber|int|float|string $value, ?Dimension $prefer = null, Unit|string|null $unit = null, ?string $locale = null): ?Quantity
    {
        $number = is_string($value) ? self::number($value, $locale) : NumberParser::tryParse($value);

        if ($number !== null) {
            return $unit === null ? null : Unitary::converter()->quantity($number, $unit);
        }

        return Unitary::converter()->parseQuantity((string) $value, $prefer, $locale ?? self::current());
    }
}
