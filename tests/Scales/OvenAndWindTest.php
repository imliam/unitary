<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Quantity;
use Unitary\Scales\Beaufort;
use Unitary\Scales\GasMark;
use Unitary\Units\Speed;
use Unitary\Units\Temperature;

it('maps gas marks to temperatures (mark 1 = 275 °F, +25 °F per mark)', function (string|int $mark, string $fahrenheit) {
    expect(GasMark::of($mark)->temperature()->to(Temperature::Fahrenheit)->toString())->toBe($fahrenheit);
})->with([
    ['1/4', '225 °F'],
    ['1/2', '250 °F'],
    [1, '275 °F'],
    [4, '350 °F'],
    [9, '475 °F'],
    [10, '500 °F'],
]);

it('gives gas mark 4 as about 180 °C', function () {
    expect(GasMark::of(4)->temperature()->toDecimal(0)->toString())->toBe('177');
});

it('finds the nearest gas mark', function () {
    expect(GasMark::fromTemperature(new Quantity(180, Temperature::Celsius))->toString())->toBe('Gas mark 4')
        ->and(GasMark::fromTemperature(new Quantity(200, Temperature::Celsius))->toString())->toBe('Gas mark 6')
        ->and(GasMark::fromTemperature(new Quantity(120, Temperature::Celsius))->toString())->toBe('Gas mark ½');
});

it('parses gas marks', function () {
    expect(GasMark::tryParse('gas mark 4')?->toString())->toBe('Gas mark 4')
        ->and(GasMark::tryParse('Gas Mark ½')?->toString())->toBe('Gas mark ½')
        ->and(GasMark::tryParse('gas 7')?->toString())->toBe('Gas mark 7')
        ->and(GasMark::tryParse('gas mark 11'))->toBeNull()
        ->and(fn () => GasMark::of(3.5))->toThrow(ParseException::class);
});

it('uses the WMO Beaufort relation v = 0.836 B^1.5', function () {
    expect(Beaufort::of(6)->speed()->toDecimal(2)->toString())->toBe('12.29')
        ->and(Beaufort::of(6)->description())->toBe('Strong breeze')
        ->and(Beaufort::of(12)->description())->toBe('Hurricane force');
});

it('classifies wind speeds into Beaufort bands', function (string $mph, int $force) {
    expect(Beaufort::fromSpeed(new Quantity($mph, Speed::MilePerHour))->force)->toBe($force);
})->with([
    ['0', 0],
    ['5', 2],
    ['20', 5],
    ['28', 6],
    ['50', 9],
    ['120', 12],
]);

it('parses Beaufort forces', function () {
    expect(Beaufort::tryParse('beaufort 6')?->force)->toBe(6)
        ->and(Beaufort::tryParse('force 9')?->force)->toBe(9)
        ->and(Beaufort::tryParse('7 bft')?->force)->toBe(7)
        ->and(Beaufort::tryParse('beaufort 13'))->toBeNull()
        ->and(fn () => Beaufort::of(-1))->toThrow(ParseException::class);
});
