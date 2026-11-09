<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Unitary\Dimension;
use Unitary\Laravel\Rules\QuantityRule;
use Unitary\Tests\Laravel\TestCase;

uses(TestCase::class);

it('reads request input the way the app’s locale writes it', function () {
    app()->setLocale('de');
    $request = Request::create('/', 'POST', ['weight' => '1,5 kg', 'height' => '5 Fuß und 10 Zoll', 'count' => '2,5']);

    expect($request->quantity('weight')?->toString())->toBe('1.5 kg')
        ->and($request->quantity('height')?->toString())->toBe('70 in')
        ->and($request->quantity('count', unit: 'kg')?->toString())->toBe('2.5 kg');
});

it('reads a default the English way, because it’s written in code', function () {
    app()->setLocale('de');

    expect(Request::create('/')->quantity('weight', '1,500 kg')?->toString())->toBe('1500 kg');
});

it('reads input in the configured locale instead of the app’s', function () {
    app()->setLocale('de');
    config(['unitary.locale' => 'en']);

    expect(Request::create('/', 'POST', ['weight' => '1,500 kg'])->quantity('weight')?->toString())->toBe('1500 kg');
});

it('validates input the way the app’s locale writes it', function (mixed $value, ?string $error) {
    app()->setLocale('de');
    $rule = QuantityRule::of(Dimension::Mass)->unit('kg')->min('1 kg')->max('30 kg');

    expect(Validator::make(['weight' => $value], ['weight' => $rule])->errors()->first() ?: null)->toBe($error);
})->with([
    ['1,5 kg', null],
    ['2,5', null],
    ['1,5 Kilogramm', null],
    ['0,5 kg', 'The weight must be at least 1 kg.'],
]);

it('validates input in a locale given to the rule', function () {
    app()->setLocale('de');
    $rule = QuantityRule::of(Dimension::Mass)->max('30 kg')->locale('en');

    expect(Validator::make(['weight' => '1,500 kg'], ['weight' => $rule])->errors()->first())->toBe('The weight must not be greater than 30 kg.');
});
