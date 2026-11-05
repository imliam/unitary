<?php

declare(strict_types=1);

namespace Unitary\Tests\Laravel\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Unitary\Dimension;
use Unitary\Laravel\Casts\AsQuantity;
use Unitary\Laravel\Casts\QuantityCast;
use Unitary\Laravel\Casts\QuantityWithUnitCast;
use Unitary\Laravel\Concerns\HasQuantities;
use Unitary\Quantity;
use Unitary\Units\Mass;

/**
 * @property \Unitary\Quantity<\Unitary\Unit>|null $weight
 * @property \Unitary\Quantity<\Unitary\Unit>|null $height
 * @property \Unitary\Quantity<\Unitary\Unit>|null $price
 * @property \Unitary\Quantity<\Unitary\Unit>|null $charge
 */
final class Parcel extends Model
{
    use HasQuantities;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weight' => AsQuantity::of(Mass::Kilogram),
            'height' => AsQuantity::withUnit(Dimension::Length),
            'price' => QuantityWithUnitCast::class.':currency,price_amount,price_currency',
            'charge' => QuantityWithUnitCast::class,
        ];
    }
}
