<?php

declare(strict_types=1);

namespace Unitary\Laravel\Casts;

use Brick\Math\BigRational;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Unitary\Dimension;
use Unitary\Laravel\InvalidQuantityException;
use Unitary\MeasurementUnit;
use Unitary\Parsing\NumberParser;
use Unitary\Quantity;
use Unitary\Unitary;

/**
 * Stores a quantity across two columns, keeping the unit it was given in:
 *
 *     'weight' => QuantityWithUnitCast::class.':mass'            // weight_value, weight_unit
 *     'weight' => AsQuantity::withUnit(Dimension::Mass, 'grams', 'grams_unit')
 *
 * The value column holds the amount in the dimension's base unit (kilograms,
 * meters, kelvin...), so ordering and filtering are correct even when rows use
 * different units. Currencies are stored as-is, since they have no fixed base.
 *
 * @implements CastsAttributes<Quantity, Quantity|string>
 */
final class QuantityWithUnitCast implements CastsAttributes, QuantityStorage
{
    private readonly ?Dimension $dimension;

    public function __construct(
        ?string $dimension = null,
        private readonly ?string $valueColumn = null,
        private readonly ?string $unitColumn = null,
    ) {
        $this->dimension = Units::dimension($dimension);
    }

    /**
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Quantity
    {
        $stored = $attributes[$this->valueColumn($key)] ?? null;
        $symbol = $attributes[$this->unitColumn($key)] ?? null;

        if ($stored === null || $stored === '' || ! is_string($symbol) || $symbol === '') {
            return null;
        }

        if (! is_string($stored) && ! is_int($stored) && ! is_float($stored)) {
            throw InvalidQuantityException::unsupported($key, $stored);
        }

        $unit = Units::resolve($symbol, $this->dimension);
        $number = NumberParser::parse($stored);

        return Unitary::converter()->quantity(
            $unit instanceof MeasurementUnit ? Units::restore($unit->fromBase($number)) : $number,
            $unit,
        );
    }

    /**
     * @return array<string, string|null>
     *
     * @param  mixed  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$this->valueColumn($key) => null, $this->unitColumn($key) => null];
        }

        $quantity = $this->quantity($key, $value);

        return [
            $this->valueColumn($key) => $this->base($quantity),
            $this->unitColumn($key) => $quantity->unit()->symbol(),
        ];
    }

    public function valueColumn(string $attribute): string
    {
        return $this->valueColumn !== null && $this->valueColumn !== '' ? $this->valueColumn : "{$attribute}_value";
    }

    public function unitColumn(string $attribute): string
    {
        return $this->unitColumn !== null && $this->unitColumn !== '' ? $this->unitColumn : "{$attribute}_unit";
    }

    public function toStoredValue(mixed $value): string
    {
        return $this->base($this->quantity('value', $value));
    }

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    private function quantity(string $key, mixed $value): Quantity
    {
        $quantity = match (true) {
            $value instanceof Quantity => $value,
            is_string($value) && NumberParser::tryParse($value) === null => Unitary::converter()->parseQuantity($value, $this->dimension),
            is_string($value), is_int($value), is_float($value) => throw InvalidQuantityException::unitRequired($key),
            default => throw InvalidQuantityException::unsupported($key, $value),
        };

        if ($this->dimension !== null && $quantity->dimension() !== $this->dimension) {
            throw InvalidQuantityException::wrongDimension($key, $this->dimension, $quantity->dimension());
        }

        return $quantity;
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>  $quantity
     */
    private function base(Quantity $quantity): string
    {
        $unit = $quantity->unit();

        return Decimal::toString($unit instanceof MeasurementUnit ? $unit->toBase($quantity->value()) : $quantity->value());
    }
}
