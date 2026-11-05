<?php

declare(strict_types=1);

namespace Unitary\Laravel;

use Brick\Math\BigNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Unitary\Dimension;
use Unitary\Exceptions\ParseException;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\Unitary;

/**
 * Implementations behind the Request and Collection macros.
 *
 * @internal
 */
final class Macros
{
    public static function register(): void
    {
        Request::macro('quantity', function (string $key, Quantity|string|null $default = null, ?Dimension $dimension = null, Unit|string|null $unit = null): ?Quantity {
            return Macros::requestQuantity($this, $key, $default, $dimension, $unit);
        });

        Collection::macro('sumQuantities', function (callable|string|null $using = null, Unit|string|null $unit = null): ?Quantity {
            return Macros::sumQuantities($this, $using, $unit);
        });
    }

    /**
     * $request->quantity('weight'), $request->quantity('weight', '0 kg', Dimension::Mass, unit: 'kg')
     *
     * @throws ParseException when the input is present but isn't a quantity
     *
     * @param  \Unitary\Quantity<\Unitary\Unit>|string|null  $default
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    public static function requestQuantity(Request $request, string $key, Quantity|string|null $default = null, ?Dimension $dimension = null, Unit|string|null $unit = null): ?Quantity
    {
        $value = $request->input($key);

        if ($value === null || $value === '') {
            $value = $default;
        }

        return match (true) {
            $value === null, $value instanceof Quantity => $value,
            $value instanceof BigNumber, is_int($value), is_float($value),
            is_string($value) && NumberParser::tryParse($value) !== null => $unit !== null
                ? Unitary::converter()->quantity($value, $unit)
                : throw ParseException::cannotParse((string) $value, 'quantity', 'a unit is required'),
            is_string($value) => Unitary::converter()->parseQuantity($value, $dimension),
            default => throw ParseException::cannotParse(get_debug_type($value), 'quantity'),
        };
    }

    /**
     * Sums quantities (or strings like "5 kg") in the first item's unit, or in
     * $unit when given. Returns null for an empty collection without a $unit.
     *
     * @param  Collection<array-key, mixed>  $items
     *
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    public static function sumQuantities(Collection $items, callable|string|null $using = null, Unit|string|null $unit = null): ?Quantity
    {
        $converter = Unitary::converter();

        $quantities = $items
            ->map(fn (mixed $item) => match (true) {
                $using === null => $item,
                is_string($using) => data_get($item, $using),
                default => $using($item),
            })
            ->filter(fn (mixed $item) => $item !== null && $item !== '')
            ->map(fn (mixed $item) => $item instanceof Quantity ? $item : $converter->parseQuantity(is_string($item) ? $item : get_debug_type($item)))
            ->values();

        $total = $unit !== null ? $converter->quantity(0, $unit) : $quantities->first();

        if ($total === null) {
            return null;
        }

        foreach ($unit !== null ? $quantities : $quantities->slice(1) as $quantity) {
            $total = $total->plus($quantity);
        }

        return $total;
    }
}
