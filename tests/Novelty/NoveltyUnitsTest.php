<?php

declare(strict_types=1);

use Unitary\Converter;
use Unitary\MeasurementUnit;
use Unitary\Unit;
use Unitary\UnitRegistry;
use Unitary\Units\Novelty\NoveltyArea;
use Unitary\Units\Novelty\NoveltyLength;
use Unitary\Units\Novelty\NoveltyMass;
use Unitary\Units\Novelty\NoveltySpeed;
use Unitary\Units\Novelty\NoveltyTime;
use Unitary\Units\Novelty\NoveltyVolume;

const NOVELTY_ENUMS = [
    NoveltyLength::class,
    NoveltyArea::class,
    NoveltyVolume::class,
    NoveltyMass::class,
    NoveltyTime::class,
    NoveltySpeed::class,
];

function noveltyConverter(): Converter
{
    return new Converter(new UnitRegistry([...UnitRegistry::DEFAULT_ENUMS, ...NOVELTY_ENUMS]));
}

it('converts between novelty and real units', function (string $input, string $expected) {
    expect(noveltyConverter()->convert($input)->toDecimal(4)->toString())->toBe($expected);
})->with([
    ['1 Burj Khalifa in bananas', '4656.9179'],
    ['1 Wales in football pitches', '2910224.0896'],
    ['2 Wales in km²', '41558.0000'],
    ['1 Texas in Wales', '33.4790'],
    ['1 Eiffel Tower in giraffes', '60.1487'],
    ['1 smoot in cm', '170.1800'],
    ['364.4 smoots in m', '620.1359'],
    ['1 marathon in football fields', '384.5418'],
    ['1 lunar distance in Earth circumferences', '9.5920'],
    ['1 Olympic swimming pool in bathtubs', '31250.0000'],
    ['1 Olympic pool in wine bottles', '3333333.3333'],
    ['1 blue whale in kg', '149685.4821'],
    ['1 blue whale in African elephants', '24.9476'],
    ['1 microcentury in min', '52.5949'],
    ['1 nanocentury in s', '3.1557'],
    ['1 moment in s', '90.0000'],
    ['1 sol in h', '24.6598'],
    ['1 lunar month in days', '29.5306'],
    ['1 furlong per fortnight in mm/s', '0.1663'],
    ['1 cheetah in snails', '11372.3077'],
    ['1 Usain Bolt in km/h', '37.5783'],
    ['1 speed of sound in mph', '767.2691'],
]);

it('resolves the same name to the right dimension from context', function () {
    $converter = noveltyConverter();

    expect($converter->convert('1 football pitch in m')->toString())->toBe('105 m')
        ->and($converter->convert('1 football pitch in m²')->toString())->toBe('7140 m²')
        ->and($converter->convert('1 double-decker bus in kg')->toString())->toBe('12650 kg')
        ->and($converter->convert('1 double-decker bus in ft')->toDecimal(2)->toString())->toBe('36.84');
});

it('parses plurals, aliases and British spellings', function (string $text, Unit $unit) {
    expect(noveltyConverter()->units()->resolve($text, $unit->dimension()))->toBe($unit);
})->with([
    ['bananas', NoveltyLength::Banana],
    ['football pitches', NoveltyLength::FootballPitch],
    ['double decker buses', NoveltyLength::DoubleDeckerBus],
    ['Routemasters', NoveltyLength::DoubleDeckerBus],
    ['size of Wales', NoveltyArea::Wales],
    ['Olympic-sized swimming pools', NoveltyVolume::OlympicSwimmingPool],
    ['elephants', NoveltyMass::AfricanElephant],
    ['microcenturies', NoveltyTime::Microcentury],
    ['Martian days', NoveltyTime::Sol],
    ["snail's pace", NoveltySpeed::Snail],
]);

it('round-trips every novelty unit exactly', function () {
    foreach (NOVELTY_ENUMS as $enum) {
        foreach ($enum::cases() as $unit) {
            assert($unit instanceof MeasurementUnit);
            $value = Brick\Math\BigRational::of('123.456');

            expect($unit->fromBase($unit->toBase($value))->isEqualTo($value))->toBeTrue($unit->symbol());
        }
    }
});

it('does not change how any built-in unit text resolves', function () {
    $plain = new UnitRegistry();
    $novelty = noveltyConverter()->units();

    foreach ($plain->all() as $unit) {
        foreach ([$unit->symbol(), $unit->name(), ...$unit->aliases()] as $text) {
            $before = array_map(fn (Unit $u) => $u->dimension()->value.':'.$u->symbol(), $plain->candidates($text));
            $after = array_map(fn (Unit $u) => $u->dimension()->value.':'.$u->symbol(), $novelty->candidates($text));

            expect(array_slice($after, 0, count($before)))->toBe($before, "\"{$text}\"");
        }
    }
});
