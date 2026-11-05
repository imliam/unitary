<?php

declare(strict_types=1);

use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Quantity;
use Unitary\Unitary;

afterEach(fn () => Unitary::setConverter(null));

it('recognizes meaningful input', function (string $input) {
    expect(Unitary::understands($input))->toBeTrue();
})->with([
    '5 ft 11 in', '£23 in USD', '23 GBP', '25% of 300', '(5 ft + 30 cm) × 2 in m', '#ff0000', 'rebeccapurple to hsl',
    '9am PST in Tokyo', 'UK 9 shoe in EU', '100c in f', '1 cup of flour in g', 'two and a half miles', '51.5072, -0.1276',
    '$100 in 1970 in today\'s money', '20 dBm to mW', '500 cp in gp',
]);

it('rejects gibberish and impossible conversions', function (string $input) {
    expect(Unitary::understands($input))->toBeFalse();
})->with(['', '   ', 'asdf', '5 florbs', '5 kg to m', 'asdf 5 qwer', 'hello world', '5 gp in USD', '1/0']);

it('only accepts calculator labels when asked', function () {
    expect(Unitary::understands('3 pizzas at $12 each'))->toBeFalse()
        ->and(Unitary::understands('3 pizzas at $12 each', allowLabels: true))->toBeTrue();
});

it('never needs exchange rates or the network', function () {
    Unitary::useExchangeRates(null);

    expect(Unitary::understands('£23 in USD'))->toBeTrue()
        ->and(Unitary::understands('¥5000 to EUR on 2020-03-01'))->toBeTrue();
});

it('converts without throwing via tryConvert', function () {
    Unitary::useExchangeRates(new FixedExchangeRates('USD', ['GBP' => '0.8']));

    expect(Unitary::tryConvert('5 florbs'))->toBeNull()
        ->and(Unitary::tryConvert('5 kg to m'))->toBeNull()
        ->and(Unitary::tryConvert(5, 'blorps'))->toBeNull()
        ->and(Unitary::tryConvert('£8 in USD'))->toBeInstanceOf(Quantity::class)
        ->and((string) Unitary::tryConvert('£8 in USD'))->toBe('10 USD');
});
