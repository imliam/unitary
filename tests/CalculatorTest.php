<?php

declare(strict_types=1);

use Unitary\Calc\Calculator;
use Unitary\Color\Color;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Exceptions\ParseException;
use Unitary\Unitary;

beforeEach(fn () => Unitary::useExchangeRates(new FixedExchangeRates('USD', ['GBP' => '0.8', 'EUR' => '0.9'])));
afterEach(fn () => Unitary::setConverter(null));

it('evaluates arithmetic over quantities', function (string $expression, string $expected) {
    expect((string) Unitary::evaluate($expression))->toBe($expected);
})->with([
    ['(5 ft + 30 cm) × 2 in m', '3.648 m'],
    ['90 km / 3 h', '30 km/h'],
    ['3 pizzas at $12 each + 20% tip', '43.2 USD'],
    ['£20 + $5 in GBP', '24 GBP'],
    ['5 m + 3', '8 m'],
    ['1/3 cup + 2 tbsp in ml', '108.4362750625 mL'],
    ['2^10', '1024'],
    ['-2^2', '-4'],
    ['2 × 3 + 4', '10'],
    ['2 × (3 + 4)', '14'],
    ['10 / 4', '2.5'],
    ['sqrt(2)', '1.4142135623730950488'],
    ['round(10 km / 3, 2)', '3.33 km'],
    ['max(1 m, 90 cm, 2 ft)', '1 m'],
    ['two and a half miles × 2 in km', '8.04672 km'],
    ['5 ft 11 in in cm', '180.34 cm'],
    ['1/2 cup of flour + 2 tbsp of butter in g', '88.25 g'],
    ['10 km/h × 30 min', '5 km'],
]);

it('treats short Japanese unit names as labels, not units', function (string $expression, string $expected) {
    expect((string) Unitary::evaluate($expression))->toBe($expected);
})->with([
    ['5 km to go', '5 km'],
    ['rent $500 se $20', '520 USD'],
    ['$8 sun cream + $12', '20 USD'],
    ['$50 fun money + $20', '70 USD'],
    ['ken 3 + 4', '7'],
    ['10 ri + 5', '15'],
]);

it('understands percentages like a notepad calculator', function (string $expression, string $expected) {
    expect((string) Unitary::evaluate($expression))->toBe($expected);
})->with([
    ['25% of 300', '75'],
    ['$55 - 10%', '49.5 USD'],
    ['$100 + 20%', '120 USD'],
    ['5% on $30', '31.5 USD'],
    ['6% off 40 EUR', '37.6 EUR'],
    ['50 is what % of 200', '25%'],
    ['$50 as a % of $100', '50%'],
    ['$70 as a % on $20', '250%'],
    ['$20 as a % off $80', '75%'],
    ['5% of what is 6 EUR', '120 EUR'],
    ['5% off what is 95', '100'],
    ['6 is 5% of what', '120'],
    ['0.25 as %', '25%'],
]);

it('keeps variables, prev and sum across a sheet', function () {
    $calculator = new Calculator();
    $rows = $calculator->evaluateSheet("rate = \$120/hr\n42 hr × rate\nprev × 2\n\n10 m\n20 m\nsum\naverage\n# a comment\n#ff0000 to hsl");

    expect(array_map(fn (array $row) => $row['result'] === null ? null : (string) $row['result'], $rows))->toBe([
        '120 USD/h', '5040 USD', '10080 USD', null, '10 m', '20 m', '30 m', '20 m', null, 'hsl(0 100% 50%)',
    ])->and($calculator->variables())->toHaveKey('rate');
});

it('falls back to other conversions', function () {
    expect(Unitary::evaluate('#ff0000 to hsl'))->toBeInstanceOf(Color::class)
        ->and((string) Unitary::evaluate('UK 9 shoe in EU'))->toBe('EU 43');
});

it('explains errors', function () {
    Unitary::evaluate('5 m + (3');
})->throws(ParseException::class);

it('refuses to add incompatible quantities', function () {
    Unitary::evaluate('5 m + 3 kg');
})->throws(\Unitary\Exceptions\IncompatibleUnitsException::class);
