<?php

declare(strict_types=1);

namespace Unitary\Laravel\Casts;

use Brick\Math\BigNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Unitary\Laravel\InvalidQuantityException;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\Unitary;

/**
 * Stores a quantity in a single column, always in one fixed unit:
 *
 *     'weight' => QuantityCast::class.':kg'
 *     'weight' => AsQuantity::of(Mass::Kilogram)
 *
 * Accepts a Quantity in any compatible unit (converted on save), freeform
 * text ("5 lb"), or a plain number in the column's unit.
 *
 * @implements CastsAttributes<Quantity, Quantity|BigNumber|string|int|float>
 */
final class QuantityCast implements CastsAttributes, QuantityStorage
{
    private readonly Unit $unit;

    public function __construct(string $unit, ?string $dimension = null)
    {
        $this->unit = Units::resolve($unit, Units::dimension($dimension));
    }

    public function unit(): Unit
    {
        return $this->unit;
    }

    /**
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Quantity
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw InvalidQuantityException::unsupported($key, $value);
        }

        return Unitary::converter()->quantity($value, $this->unit);
    }

    /**
     * @param  mixed  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : $this->stored($key, $value);
    }

    public function valueColumn(string $attribute): string
    {
        return $attribute;
    }

    public function toStoredValue(mixed $value): string
    {
        return $this->stored('value', $value);
    }

    private function stored(string $key, mixed $value): string
    {
        $converter = Unitary::converter();

        $quantity = match (true) {
            $value instanceof Quantity => $value,
            $value instanceof BigNumber, is_int($value), is_float($value) => $converter->quantity($value, $this->unit),
            is_string($value) => NumberParser::tryParse($value) !== null
                ? $converter->quantity($value, $this->unit)
                : $converter->parseQuantity($value, $this->unit->dimension()),
            default => throw InvalidQuantityException::unsupported($key, $value),
        };

        return Decimal::toString($quantity->to($this->unit)->value());
    }
}
