<?php

declare(strict_types=1);

/*
 * Built-in substances. Every figure is quoted from the cited source (retrieved 2026-10-01).
 *
 * density: [mass, volume] as written by the source (e.g. King Arthur's "1/2 cup = 156 g").
 * energy:  [energy, per] content per volume or mass, with its basis.
 * co2:     [CO₂ mass, per] combustion emissions.
 * molarMass: [mass, amount] for converting moles, and mmol/L to mg/dL.
 */

$kingArthur = 'King Arthur Baking, Ingredient Weight Chart: https://www.kingarthurbaking.com/learn/ingredient-weight-chart (retrieved 2026-10-01)';
$usgs = 'USGS Water Science School, Water Density ("1 gram per cubic centimeter"): https://www.usgs.gov/water-science-school/science/water-density (retrieved 2026-10-01)';
$epaVehicle = 'US EPA, Greenhouse Gas Emissions from a Typical Passenger Vehicle: https://www.epa.gov/greenvehicles/greenhouse-gas-emissions-typical-passenger-vehicle (retrieved 2026-10-01)';
$epaMpge = 'US EPA MPGe conversion factor, 33.705 kWh per gallon of gasoline equivalent: https://www.epa.gov/greenvehicles/technology-learn-more-about-technology-assumptions-choose-path-tool (EPA 420-F-11-017; retrieved 2026-10-01)';
$cldrGlucose = 'Unicode CLDR units.xml, glucose_molar_mass 180.1557 g/mol, from CIAAW Standard Atomic Weights 2019: https://github.com/unicode-org/cldr/blob/main/common/supplemental/units.xml (retrieved 2026-11-05)';
$afdc = 'US DOE Alternative Fuels Data Center, Fuel Properties Comparison: https://afdc.energy.gov/files/u/publication/fuel_comparison_chart.pdf (retrieved 2026-10-01)';
$epaHub = 'US EPA, Emission Factors for Greenhouse Gas Inventories (GHG Emission Factors Hub, 15 January 2025), Tables 1 and 2: https://www.epa.gov/system/files/documents/2025-01/ghg-emission-factors-hub-2025.pdf (retrieved 2026-10-01)';

$ingredient = fn (string $name, array $aliases, string $mass, string $volume) => [
    'name' => $name,
    'aliases' => $aliases,
    'density' => [$mass, $volume],
    'sources' => ['density' => $kingArthur],
];

$hub = fn (string $name, array $aliases, string $mmbtuPerGallon, string $kgCo2PerGallon) => [
    'name' => $name,
    'aliases' => $aliases,
    // The hub quotes million Btu (MMBtu) per gallon.
    'energy' => [$mmbtuPerGallon.'e6 BTU', '1 gal'],
    'energyBasis' => 'higher heating value (EPA GHG Emission Factors Hub, Table 1)',
    'co2' => [$kgCo2PerGallon.' kg', '1 gal'],
    'sources' => ['energy' => $epaHub, 'co2' => $epaHub],
];

return [
    [
        'name' => 'water',
        'aliases' => [],
        'density' => ['1 g', '1 mL'],
        'sources' => ['density' => $usgs],
    ],
    $ingredient('milk', ['fresh milk', 'whole milk'], '227 g', '1 cup'),
    $ingredient('heavy cream', ['cream', 'double cream', 'whipping cream', 'light cream', 'half and half', 'half & half'], '227 g', '1 cup'),
    $ingredient('yogurt', ['yoghurt', 'yogourt'], '227 g', '1 cup'),
    $ingredient('all-purpose flour', ['all purpose flour', 'flour', 'plain flour', 'ap flour'], '120 g', '1 cup'),
    $ingredient('bread flour', ['strong flour', 'strong white flour'], '120 g', '1 cup'),
    $ingredient('whole wheat flour', ['wholewheat flour', 'whole-wheat flour', 'wholemeal flour'], '113 g', '1 cup'),
    $ingredient('granulated sugar', ['sugar', 'white sugar', 'granulated white sugar'], '198 g', '1 cup'),
    $ingredient('superfine sugar', ['caster sugar', 'castor sugar', "baker's special sugar"], '190 g', '1 cup'),
    $ingredient('brown sugar', ['packed brown sugar', 'light brown sugar', 'dark brown sugar'], '213 g', '1 cup'),
    $ingredient("confectioners' sugar", ['confectioners sugar', 'powdered sugar', 'icing sugar'], '113 g', '1 cup'),
    $ingredient('butter', [], '113 g', '8 tbsp'),
    $ingredient('honey', [], '21 g', '1 tbsp'),
    $ingredient('maple syrup', [], '156 g', '1/2 cup'),
    $ingredient('olive oil', [], '50 g', '1/4 cup'),
    $ingredient('vegetable oil', ['oil', 'canola oil', 'sunflower oil'], '198 g', '1 cup'),
    $ingredient('rolled oats', ['oats', 'old-fashioned oats', 'quick oats', 'porridge oats'], '89 g', '1 cup'),
    $ingredient('long grain rice', ['rice', 'white rice', 'uncooked rice', 'dry rice'], '99 g', '1/2 cup'),
    $ingredient('cocoa powder', ['cocoa', 'unsweetened cocoa'], '42 g', '1/2 cup'),
    $ingredient('table salt', ['salt'], '18 g', '1 tbsp'),
    $ingredient('baking powder', [], '4 g', '1 tsp'),
    $ingredient('baking soda', ['bicarbonate of soda', 'bicarb', 'sodium bicarbonate'], '3 g', '1/2 tsp'),
    $ingredient('cornstarch', ['corn starch', 'cornflour'], '28 g', '1/4 cup'),
    $ingredient('chocolate chips', ['chocolate chip', 'choc chips'], '170 g', '1 cup'),
    $ingredient('peanut butter', [], '135 g', '1/2 cup'),
    $ingredient('chopped walnuts', ['walnuts', 'walnut'], '113 g', '1 cup'),
    $ingredient('diced pecans', ['pecans', 'pecan', 'chopped pecans'], '57 g', '1/2 cup'),

    [
        'name' => 'gasoline',
        'aliases' => ['petrol', 'motor gasoline', 'gasolene'],
        'energy' => ['33.705 kWh', '1 gal'],
        'energyBasis' => 'EPA gasoline gallon equivalent (the MPGe basis)',
        'co2' => ['8887 g', '1 gal'],
        'sources' => ['energy' => $epaMpge, 'co2' => $epaVehicle],
    ],
    [
        'name' => 'diesel',
        'aliases' => ['diesel fuel', 'low sulfur diesel', 'derv'],
        'energy' => ['128488 BTU', '1 gal'],
        'energyBasis' => 'lower heating value (AFDC Fuel Properties Comparison)',
        'co2' => ['10180 g', '1 gal'],
        'sources' => ['energy' => $afdc, 'co2' => $epaVehicle],
    ],
    [
        'name' => 'glucose',
        'aliases' => ['blood glucose', 'blood sugar', 'dextrose', 'd-glucose'],
        'molarMass' => ['180.1557 g', '1 mol'],
        'sources' => ['molarMass' => $cldrGlucose],
    ],
    $hub('kerosene', ['paraffin', 'paraffin oil', 'lamp oil'], '0.135', '10.15'),
    $hub('jet fuel', ['kerosene-type jet fuel', 'jet a', 'jet a-1', 'avtur', 'aviation kerosene'], '0.135', '9.75'),
    $hub('heating oil', ['home heating oil', 'fuel oil', 'distillate fuel oil', 'distillate fuel oil no. 2', 'gas oil'], '0.138', '10.21'),
    $hub('propane', ['liquid propane'], '0.091', '5.72'),
    $hub('ethanol', ['ethyl alcohol', 'e100'], '0.084', '5.75'),
    $hub('biodiesel', ['b100'], '0.128', '9.45'),
];
