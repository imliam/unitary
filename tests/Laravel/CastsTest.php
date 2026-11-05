<?php

declare(strict_types=1);

use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Laravel\InvalidQuantityException;
use Unitary\Quantity;
use Unitary\Tests\Laravel\Fixtures\Parcel;
use Unitary\Tests\Laravel\TestCase;
use Unitary\Units\ElectricCharge;
use Unitary\Units\Length;
use Unitary\Units\Mass;

uses(TestCase::class);

it('stores a single-column quantity in its fixed unit', function () {
    $parcel = Parcel::create(['weight' => Quantity::of(5, 'lb')]);

    expect($parcel->getAttributes()['weight'])->toBe('2.26796185')
        ->and($parcel->fresh()?->weight?->unit())->toBe(Mass::Kilogram)
        ->and((string) $parcel->fresh()?->weight?->toDecimal())->toBe('2.26796185');
});

it('accepts freeform text and plain numbers in the column unit', function () {
    expect(Parcel::create(['weight' => '2 lb 3 oz'])->fresh()?->weight?->to('oz')->toString())->toBe('35 oz')
        ->and(Parcel::create(['weight' => 12.5])->fresh()?->weight?->toString())->toBe('12.5 kg')
        ->and(Parcel::create(['weight' => '7'])->fresh()?->weight?->toString())->toBe('7 kg')
        ->and(Parcel::create(['weight' => null])->fresh()?->weight)->toBeNull();
});

it('refuses quantities of the wrong dimension', function () {
    Parcel::create(['weight' => Quantity::of(5, 'm')]);
})->throws(IncompatibleUnitsException::class);

it('keeps the original unit across two columns, storing the base value', function () {
    $parcel = Parcel::create(['height' => '5 ft 11 in']);
    $attributes = $parcel->getAttributes();

    expect($attributes['height_value'])->toBe('1.8034')
        ->and($attributes['height_unit'])->toBe('in')
        ->and($parcel->fresh()?->height?->toString())->toBe('71 in')
        ->and($parcel->fresh()?->height?->unit())->toBe(Length::Inch);
});

it('restores values whose base form was rounded', function () {
    expect(Parcel::create(['height' => '1/3 mi'])->fresh()?->height?->toString())->toBe('0.333333333333333 mi')
        ->and(Parcel::create(['height' => '1/3 ft'])->getAttributes()['height_value'])->toBe('0.1016');
});

it('refuses the wrong dimension in two-column casts', function () {
    Parcel::create(['height' => '5 lb']);
})->throws(InvalidQuantityException::class, 'expects a length quantity, mass given');

it('round-trips symbols shared with aliases and custom column names', function () {
    $parcel = Parcel::create([
        'charge' => Quantity::of(3, ElectricCharge::Coulomb),
        'price' => '£23',
    ]);

    expect($parcel->fresh()?->charge?->unit())->toBe(ElectricCharge::Coulomb)
        ->and($parcel->getAttributes()['price_amount'])->toBe('23')
        ->and($parcel->getAttributes()['price_currency'])->toBe('GBP')
        ->and($parcel->fresh()?->price?->format())->toBe('£23.00');
});

it('requires a unit for bare numbers in two-column casts', function () {
    Parcel::create(['height' => 5]);
})->throws(InvalidQuantityException::class, 'needs a unit');

it('sorts and filters by base value across units', function () {
    foreach (['2 m', '150 cm', '7 ft', '30 in', '1 yd'] as $height) {
        Parcel::create(['height' => $height]);
    }

    expect(Parcel::query()->orderByQuantity('height')->get()->map(fn (Parcel $p) => $p->height?->toString())->all())
        ->toBe(['30 in', '1 yd', '150 cm', '2 m', '7 ft'])
        ->and(Parcel::query()->whereQuantity('height', '>', '5 ft')->count())->toBe(2)
        ->and(Parcel::query()->whereQuantity('height', '1 yd')->count())->toBe(1)
        ->and(Parcel::query()->whereQuantityBetween('height', '1 m', '2 m')->count())->toBe(2)
        ->and(Parcel::query()->orderByQuantity('height', 'desc')->first()?->height?->toString())->toBe('7 ft');
});

it('converts scope operands into a single-column unit', function () {
    Parcel::create(['weight' => '1 kg']);
    Parcel::create(['weight' => '10 lb']);

    expect(Parcel::query()->whereQuantity('weight', '>', '5 lb')->count())->toBe(1)
        ->and(Parcel::query()->whereQuantity('weight', '<', 3)->count())->toBe(1)
        ->and(Parcel::query()->whereQuantity('weight', '>', '5 lb')->orWhereQuantity('weight', '1000 g')->count())->toBe(2);
});
