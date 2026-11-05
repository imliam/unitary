<?php

declare(strict_types=1);

use Unitary\Quantity;
use Unitary\Substances\Substance;
use Unitary\Substances\SubstanceConverter;
use Unitary\Substances\SubstanceException;
use Unitary\Substances\Substances;
use Unitary\Unitary;

afterEach(fn () => Substances::reset());

function substance(string $input, string $target): string
{
    $parsed = SubstanceConverter::tryParse($input) ?? throw new RuntimeException("No substance in \"{$input}\"");

    return SubstanceConverter::convert(Unitary::converter()->parseQuantity($parsed['amount']), $parsed['substance'], $target)->toString();
}

it('converts ingredient volumes to masses using King Arthur Baking figures', function (string $input, string $target, string $expected) {
    expect(substance($input, $target))->toBe($expected);
})->with([
    ['1 cup of flour', 'g', '120 g'],
    ['2 cups flour', 'g', '240 g'],
    ['1 cup of plain flour', 'g', '120 g'],
    ['1 cup bread flour', 'g', '120 g'],
    ['1 cup whole wheat flour', 'g', '113 g'],
    ['1 cup of sugar', 'g', '198 g'],
    ['1 cup caster sugar', 'g', '190 g'],
    ['1 cup icing sugar', 'g', '113 g'],
    ['1 cup packed brown sugar', 'g', '213 g'],
    ['8 tbsp butter', 'g', '113 g'],
    ['1 tbsp honey', 'g', '21 g'],
    ['1/2 cup maple syrup', 'g', '156 g'],
    ['1/4 cup olive oil', 'g', '50 g'],
    ['1/2 cup cocoa', 'g', '42 g'],
    ['1 tbsp salt', 'g', '18 g'],
    ['1 tsp baking powder', 'g', '4 g'],
    ['1/2 tsp bicarbonate of soda', 'g', '3 g'],
    ['1 cup chocolate chips', 'g', '170 g'],
    ['1/2 cup peanut butter', 'g', '135 g'],
    ['1 cup double cream', 'g', '227 g'],
]);

it('converts masses back to volumes', function () {
    expect(substance('240 g of flour', 'cup'))->toBe('2 cup')
        ->and(substance('396 g sugar', 'cups'))->toBe('2 cup')
        ->and(substance('1 kg of water', 'L'))->toBe('1 L');
});

it('uses the USGS density for water', function () {
    expect(substance('1 L of water', 'kg'))->toBe('1 kg')
        ->and(substance('1 gal water', 'lb'))->toBe('8.3454044520193318067 lb');
});

it('converts fuel volumes to energy', function () {
    expect(substance('1 gal of gasoline', 'kWh'))->toBe('33.705 kWh')
        ->and(substance('1 gallon of petrol', 'kWh'))->toBe('33.705 kWh')
        ->and(substance('1 gal diesel', 'BTU'))->toBe('128488 BTU')
        ->and(substance('1 gal kerosene', 'BTU'))->toBe('135000 BTU')
        ->and(substance('1 L of paraffin', 'MJ'))->toBe('37.626696441778710329 MJ');
});

it('converts fuel volumes to combustion CO2 per the EPA', function (string $input, string $target, string $expected) {
    expect(substance($input, $target))->toBe($expected);
})->with([
    ['1 gal gasoline', 'kg CO2', '8.887 kg'],
    ['1 gal of diesel', 'kg CO₂', '10.18 kg'],
    ['1 gal jet fuel', 'kg of CO2', '9.75 kg'],
    ['1 gal heating oil', 'kg carbon dioxide', '10.21 kg'],
    ['1 gal propane', 'kg CO2e', '5.72 kg'],
    ['1 gal ethanol', 'g CO2', '5750 g'],
    ['1000 gal biodiesel', 'tonnes CO2', '9.45 t'],
]);

it('converts energy back into fuel volume', function () {
    $energy = Unitary::converter()->quantity('33.705', 'kWh');

    expect(SubstanceConverter::convert($energy, Substances::find('gasoline') ?? throw new RuntimeException(), 'gal')->toString())->toBe('1 gal');
});

it('round-trips volume, mass and energy exactly', function () {
    $flour = Substances::find('flour') ?? throw new RuntimeException();
    $cups = Unitary::converter()->quantity(3, 'cup');
    $grams = SubstanceConverter::convert($cups, $flour, 'g');

    expect(SubstanceConverter::convert($grams, $flour, 'cup')->value()->isEqualTo(3))->toBeTrue();
});

it('explains missing properties', function () {
    expect(fn () => substance('1 cup flour', 'kWh'))->toThrow(SubstanceException::class, 'No energy content is known for all-purpose flour')
        ->and(fn () => substance('1 gal gasoline', 'kg'))->toThrow(SubstanceException::class, 'No density is known for gasoline')
        ->and(fn () => substance('1 cup sugar', 'kg CO2'))->toThrow(SubstanceException::class, 'No CO₂ emission factor is known for granulated sugar')
        ->and(fn () => substance('1 cup sugar', 'm'))->toThrow(SubstanceException::class, 'Cannot convert volume of granulated sugar to length');
});

it('splits amounts from substance names', function () {
    expect(SubstanceConverter::tryParse('1 cup of flour'))->toMatchArray(['amount' => '1 cup'])
        ->and(SubstanceConverter::tryParse('a cup of sugar')['amount'] ?? null)->toBe('a cup')
        ->and(SubstanceConverter::tryParse('1 cup 2 tbsp olive oil')['substance']->name ?? null)->toBe('olive oil')
        ->and(SubstanceConverter::tryParse('2 cups chocolate chips')['substance']->name ?? null)->toBe('chocolate chips')
        ->and(SubstanceConverter::tryParse('3 Tbsp Butter')['substance']->name ?? null)->toBe('butter');
});

it('leaves ordinary quantities alone', function (string $input) {
    expect(SubstanceConverter::tryParse($input))->toBeNull();
})->with(['5 ft 11 in', '1 fl oz', '2 lb', '£23', '23 GBP', '100 km/h', '13\' 11"', 'flour', '10:00 Europe/London', '#fff']);

it('finds substances case-insensitively with plurals and British names', function () {
    expect(Substances::find('Petrol')?->name)->toBe('gasoline')
        ->and(Substances::find('paraffin')?->name)->toBe('kerosene')
        ->and(Substances::find('Icing Sugar')?->name)->toBe("confectioners' sugar")
        ->and(Substances::find('pecans')?->name)->toBe('diced pecans')
        ->and(Substances::find('yoghurt')?->name)->toBe('yogurt')
        ->and(Substances::find('unobtainium'))->toBeNull();
});

it('cites a source for every property', function () {
    foreach (Substances::all() as $substance) {
        foreach (['density', 'energy', 'co2', 'molarMass'] as $property) {
            if ($substance->{$property} !== null) {
                expect($substance->sources())->toHaveKey($property);
            }
        }
    }

    expect(Substances::all())->toHaveCount(36);
});

it('accepts custom substances', function () {
    Substances::register(Substance::define('sand', ['dry sand'], density: ['1600 kg', '1 m³'], sources: ['density' => 'test']));

    expect(substance('2 m³ of dry sand', 't'))->toBe('3.2 t')
        ->and(Substances::find('sand'))->toBeInstanceOf(Substance::class);
});

it('reports energy bases', function () {
    expect(Substances::find('gasoline')?->energyBasis)->toContain('MPGe')
        ->and(Substances::find('diesel')?->energyBasis)->toContain('lower heating value')
        ->and(Substances::find('kerosene')?->energyBasis)->toContain('higher heating value')
        ->and(Substances::find('kerosene')?->energy)->toBeInstanceOf(Quantity::class);
});

it('converts between molar and mass concentrations with a molar mass', function (string $input, string $target, string $expected) {
    expect(substance($input, $target))->toBe($expected);
})->with([
    ['5.5 mmol/L of glucose', 'mg/dL', '99.085635 mg/dL'],
    ['99.085635 mg/dL of blood glucose', 'mmol/L', '5.5 mM'],
    ['1 mol of glucose', 'g', '180.1557 g'],
    ['180.1557 g of glucose', 'mol', '1 mol'],
]);

it('explains a missing molar mass', function () {
    substance('1 cup of flour', 'mol');
})->throws(SubstanceException::class, 'No molar mass is known for all-purpose flour');
