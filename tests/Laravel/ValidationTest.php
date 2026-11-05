<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Unitary\Dimension;
use Unitary\Laravel\Rules\CurrencyRule;
use Unitary\Laravel\Rules\QuantityRule;
use Unitary\Laravel\Rules\UnitRule;
use Unitary\Tests\Laravel\TestCase;

uses(TestCase::class);

function firstError(array $data, array $rules): ?string
{
    return Validator::make($data, $rules)->errors()->first() ?: null;
}

it('validates quantities with rule objects', function (mixed $value, ?string $error) {
    $rule = new QuantityRule(Dimension::Mass)->min('1 g')->max('30 kg');

    expect(firstError(['weight' => $value], ['weight' => $rule]))->toBe($error);
})->with([
    ['5 kg', null],
    ['2 lb 3 oz', null],
    ['31 kg', 'The weight must not be greater than 30 kg.'],
    ['0.5 g', 'The weight must be at least 1 g.'],
    ['5 m', 'The weight must be a valid mass.'],
    ['five', 'The weight must be a valid quantity, such as "5 kg".'],
    ['5', 'The weight must be a valid quantity, such as "5 kg".'],
]);

it('interprets bare numbers in a default unit', function () {
    $rule = QuantityRule::of(Dimension::Mass)->unit('kg')->max('30 kg');

    expect(firstError(['weight' => '5'], ['weight' => $rule]))->toBeNull()
        ->and(firstError(['weight' => 50], ['weight' => $rule]))->toBe('The weight must not be greater than 30 kg.');
});

it('validates quantities with string rules', function (mixed $value, ?string $error) {
    expect(firstError(['weight' => $value], ['weight' => 'required|quantity:mass,min:1g,max:30kg']))->toBe($error);
})->with([
    ['5 kg', null],
    ['31kg', 'The weight must not be greater than 30 kg.'],
    ['0.1 g', 'The weight must be at least 1 g.'],
    ['5 litres', 'The weight must be a valid mass.'],
    ['heavy', 'The weight must be a valid quantity, such as "5 kg".'],
]);

it('reports different failures for different attributes in one validator', function () {
    $errors = Validator::make(
        ['a' => '31 kg', 'b' => '0 g', 'c' => '1 m'],
        ['a' => 'quantity:mass,max:30kg', 'b' => 'quantity:mass,min:1g', 'c' => 'quantity:length,unit:m'],
    )->errors();

    expect($errors->first('a'))->toBe('The a must not be greater than 30 kg.')
        ->and($errors->first('b'))->toBe('The b must be at least 1 g.')
        ->and($errors->has('c'))->toBeFalse();
});

it('validates units and currencies', function () {
    expect(firstError(['u' => 'kilometres'], ['u' => 'unit:length']))->toBeNull()
        ->and(firstError(['u' => 'kg'], ['u' => 'unit:length']))->toBe('The u must be a valid unit of length.')
        ->and(firstError(['u' => 'florbs'], ['u' => 'unit']))->toBe('The u must be a valid unit.')
        ->and(firstError(['u' => 'kg'], ['u' => new UnitRule()]))->toBeNull()
        ->and(firstError(['c' => 'gbp'], ['c' => 'currency']))->toBeNull()
        ->and(firstError(['c' => 'XYZ'], ['c' => 'currency']))->toBe('The c must be a valid ISO 4217 currency code.')
        ->and(firstError(['c' => 'EUR'], ['c' => new CurrencyRule()]))->toBeNull();
});

it('uses the displayable attribute name and allows translation overrides', function () {
    app('translator')->addLines(['validation.max' => 'Too heavy: :attribute (:max max)'], 'en', 'unitary');

    expect(Validator::make(['parcel_weight' => '50 kg'], ['parcel_weight' => 'quantity:mass,max:30kg'], [], ['parcel_weight' => 'parcel weight'])->errors()->first())
        ->toBe('Too heavy: parcel weight (30 kg max)');
});
