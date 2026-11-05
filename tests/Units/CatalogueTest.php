<?php

declare(strict_types=1);

use Unitary\MeasurementUnit;
use Unitary\Unit;
use Unitary\UnitRegistry;

dataset('units', function () {
    foreach (UnitRegistry::default()->all() as $unit) {
        yield $unit->dimension()->value.' '.$unit->symbol() => [$unit];
    }
});

it('resolves every unit from its own symbol', function (Unit $unit) {
    if ($unit->symbol() === '') {
        expect($unit)->toBe(Unitary\Units\Ratio::One);

        return;
    }

    expect(UnitRegistry::default()->resolve($unit->symbol(), $unit->dimension()))->toBe($unit);
})->with('units');

it('resolves every unit from its name', function (Unit $unit) {
    expect(UnitRegistry::default()->resolve($unit->name(), $unit->dimension()))->toBe($unit);
})->with('units');

it('resolves every alias to a unit with the same meaning', function (Unit $unit) {
    foreach ($unit->aliases() as $alias) {
        $resolved = UnitRegistry::default()->resolve($alias, $unit->dimension());

        expect($resolved->dimension())->toBe($unit->dimension(), "alias \"{$alias}\"");

        if ($unit instanceof MeasurementUnit && $resolved instanceof MeasurementUnit) {
            expect($resolved->definition()->factor->isEqualTo($unit->definition()->factor))
                ->toBeTrue("alias \"{$alias}\" of {$unit->symbol()} resolved to {$resolved->symbol()}");
        } else {
            expect($resolved)->toBe($unit, "alias \"{$alias}\"");
        }
    }
})->with('units');

it('round-trips one of every unit through its base unit exactly', function (Unit $unit) {
    if (! $unit instanceof MeasurementUnit) {
        expect(true)->toBeTrue();

        return;
    }

    $value = Brick\Math\BigRational::of('123.456');

    expect($unit->fromBase($unit->toBase($value))->isEqualTo($value))->toBeTrue();
})->with('units');
