#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Refreshes resources/cldr/ from CLDR's units.xml:
 *
 *  - unit-preferences.php: which unit each region uses for each kind of value
 *    (road distances, people's heights, the weather...), with CLDR's
 *    thresholds and rounding increments.
 *  - unit-ids.php: the CLDR id of each Unitary unit ("km/h" → "kilometer-per-hour"),
 *    which ext-intl needs to write unit names in other languages. Each id's
 *    conversion factor is checked against Unitary's before it's included.
 *
 * Usage: php bin/update-cldr-data.php [path-or-url-to-units.xml]
 */

use Unitary\CompoundUnit;
use Unitary\Currency\Http\NativeHttpClient;
use Unitary\MeasurementUnit;
use Unitary\UnitRegistry;

require dirname(__DIR__).'/vendor/autoload.php';

$source = $argv[1] ?? 'https://raw.githubusercontent.com/unicode-org/cldr/main/common/supplemental/units.xml';
$xml = str_starts_with($source, 'https://')
    ? (new NativeHttpClient(timeout: 60))->get($source, ['User-Agent' => 'unitary-php CLDR data refresh (https://github.com/imliam/unitary)'])
    : (string) file_get_contents($source);

// CLDR unit IDs to Unitary symbols. Mixed units ("foot-and-inch") become lists.
$units = [
    'acre' => 'ac', 'barrel' => 'bbl', 'celsius' => '°C', 'centimeter' => 'cm', 'centimeter-per-hour' => 'cm/h',
    'cubic-centimeter' => 'cm³', 'cubic-foot' => 'ft³', 'cubic-inch' => 'in³', 'cubic-meter' => 'm³', 'cup' => 'cup',
    'day' => 'd', 'fahrenheit' => '°F', 'fluid-ounce' => 'fl oz', 'fluid-ounce-imperial' => 'imp fl oz',
    'foodcalorie' => 'kcal', 'foot' => 'ft', 'gallon' => 'gal', 'gallon-imperial' => 'imp gal', 'gigawatt' => 'GW',
    'gram' => 'g', 'hectare' => 'ha', 'hectopascal' => 'hPa', 'horsepower' => 'hp', 'hour' => 'h', 'inch' => 'in',
    'inch-ofhg' => 'inHg', 'inch-per-hour' => 'in/h', 'kilocalorie' => 'kcal', 'kilogram' => 'kg',
    'kilogram-per-cubic-meter' => 'kg/m³', 'kilometer' => 'km', 'kilometer-per-hour' => 'km/h',
    'kilowatt' => 'kW', 'kilowatt-hour' => 'kWh', 'liter' => 'L', 'liter-per-100-kilometer' => 'L/100km',
    // CLDR's ID reads "litres per kilometre", but these countries quote kilometres per litre.
    'liter-per-kilometer' => 'km/L',
    'megapascal' => 'MPa', 'megawatt' => 'MW', 'meter' => 'm', 'meter-per-second' => 'm/s', 'microgram' => 'µg',
    'microsecond' => 'µs', 'mile' => 'mi', 'mile-per-gallon' => 'mpg', 'mile-per-gallon-imperial' => 'mpg (imp)',
    'mile-per-hour' => 'mph', 'mile-scandinavian' => 'scand mil', 'millibar' => 'mbar',
    'milligram' => 'mg', 'milligram-ofglucose-per-deciliter' => 'mg/dL', 'milliliter' => 'mL', 'millimeter' => 'mm',
    'millimeter-ofhg' => 'mmHg', 'millimeter-per-hour' => 'mm/h', 'millimole-per-liter' => 'mmol/L',
    'millisecond' => 'ms', 'milliwatt' => 'mW', 'minute' => 'min', 'month' => 'mo', 'month-person' => 'mo',
    'nanosecond' => 'ns', 'newton' => 'N', 'ounce' => 'oz', 'pascal' => 'Pa', 'pint' => 'pint', 'pound' => 'lb',
    'pound-force-per-square-inch' => 'psi', 'quart' => 'qt', 'second' => 's', 'square-centimeter' => 'cm²',
    'square-foot' => 'ft²', 'square-inch' => 'in²', 'square-kilometer' => 'km²', 'square-meter' => 'm²',
    'square-mile' => 'mi²', 'stone' => 'st', 'tablespoon' => 'tbsp', 'teaspoon' => 'tsp', 'ton' => 'ton', 'tonne' => 't',
    'watt' => 'W', 'yard' => 'yd', 'year' => 'yr', 'year-person' => 'yr',
];

// Counts of things ("item-per-cubic-meter") have no Unitary unit; their usages are left out.
$unsupported = ['item-per-cubic-meter'];

$registry = UnitRegistry::default();
$document = new SimpleXMLElement($xml);
$preferences = [];

foreach ($document->unitPreferenceData->unitPreferences as $group) {
    $category = (string) $group['category'];
    $usage = (string) $group['usage'];
    $rows = [];

    foreach ($group->unitPreference as $preference) {
        $id = trim((string) $preference);

        if (in_array($id, $unsupported, true)) {
            continue 2;
        }

        $symbols = array_map(fn (string $part) => $units[$part] ?? throw new RuntimeException("No Unitary unit for CLDR unit \"{$part}\" ({$category}/{$usage})"), explode('-and-', $id));

        foreach ($symbols as $symbol) {
            $registry->resolve($symbol);
        }

        $increment = null;

        if (isset($preference['skeleton'])) {
            preg_match('#^precision-increment/(\d+)$#', (string) $preference['skeleton'], $match) === 1
                ?: throw new RuntimeException("Unsupported skeleton \"{$preference['skeleton']}\"");
            $increment = $match[1];
        }

        $rows[] = [
            'regions' => preg_split('/\s+/', trim((string) $preference['regions'])),
            'units' => $symbols,
            'geq' => isset($preference['geq']) ? (string) $preference['geq'] : '1',
            'increment' => $increment,
        ];
    }

    $preferences[$category][$usage] = $rows;
}

ksort($preferences);

$data = [
    'source' => 'Unicode CLDR, common/supplemental/units.xml: https://github.com/unicode-org/cldr/blob/main/common/supplemental/units.xml',
    'retrieved' => gmdate('Y-m-d'),
    'preferences' => $preferences,
];

write_data('unit-preferences.php', $data);
printf("unit-preferences.php: %d categories, %d usages\n", count($preferences), array_sum(array_map('count', $preferences)));

/**
 * @param  array<string, mixed>  $data
 */
function write_data(string $file, array $data): void
{
    $path = dirname(__DIR__).'/resources/cldr/'.$file;

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }

    file_put_contents($path, "<?php\n\n// Generated by bin/update-cldr-data.php. Do not edit by hand.\n\nreturn ".var_export($data, true).";\n");
}

// Unit ids. CLDR factors are expressions over named constants ("lb_to_kg*14");
// "a/b*c" means a ÷ (b × c), so everything after the first slash is the denominator.
$constants = [];

$evaluate = function (string $expression) use (&$constants): float {
    $product = function (string $terms) use (&$constants): float {
        $value = 1.0;

        foreach (explode('*', $terms) as $term) {
            $value *= $constants[$term] ?? (float) $term;
        }

        return $value;
    };
    $parts = explode('/', str_replace(' ', '', $expression));
    $value = $product((string) array_shift($parts));

    foreach ($parts as $part) {
        $value /= $product($part);
    }

    return $value;
};

foreach ($document->unitConstants->unitConstant as $constant) {
    $constants[(string) $constant['constant']] = $evaluate((string) $constant['value']);
}

// Factors relative to each CLDR base unit. Years are a base of their own in CLDR, so pin them to seconds.
$factors = [];
$prefixable = [];

foreach ($document->convertUnits->convertUnit as $convert) {
    $source = (string) $convert['source'];
    $factors[$source] = (isset($convert['factor']) ? $evaluate((string) $convert['factor']) : 1.0) * ((string) $convert['baseUnit'] === 'year' ? 31556952.0 : 1.0);

    if (str_contains((string) $convert['systems'], 'prefixable')) {
        $prefixable[$source] = true;
    }
}

$prefixes = [
    'quetta' => 1e30, 'ronna' => 1e27, 'yotta' => 1e24, 'zetta' => 1e21, 'exa' => 1e18, 'peta' => 1e15, 'tera' => 1e12,
    'giga' => 1e9, 'mega' => 1e6, 'kilo' => 1e3, 'hecto' => 1e2, 'deka' => 1e1, 'deci' => 1e-1, 'centi' => 1e-2,
    'milli' => 1e-3, 'micro' => 1e-6, 'nano' => 1e-9, 'pico' => 1e-12, 'femto' => 1e-15, 'atto' => 1e-18,
    'zepto' => 1e-21, 'yocto' => 1e-24, 'ronto' => 1e-27, 'quecto' => 1e-30,
    'kibi' => 2 ** 10, 'mebi' => 2 ** 20, 'gibi' => 2 ** 30, 'tebi' => 2 ** 40, 'pebi' => 2 ** 50, 'exbi' => 2 ** 60, 'zebi' => 2 ** 70, 'yobi' => 2 ** 80,
];
$sources = array_keys($factors);
usort($sources, fn (string $a, string $b) => strlen($b) <=> strlen($a));

// The factor of a CLDR unit id ("kilometer-per-hour", "square-foot"), or null if CLDR doesn't know it.
$factorOf = function (string $id) use ($factors, $prefixable, $prefixes, $sources): ?float {
    $factor = 1.0;

    foreach (explode('-per-', $id, 2) as $side => $text) {
        while ($text !== '') {
            $power = 1;

            if (preg_match('/^(square|cubic|pow(\d+))-/', $text, $match) === 1) {
                $power = match ($match[1]) { 'square' => 2, 'cubic' => 3, default => (int) $match[2] };
                $text = substr($text, strlen($match[0]));
            }

            $found = null;

            foreach ($sources as $source) {
                foreach (['' => 1.0, ...$prefixes] as $prefix => $scale) {
                    if (($prefix === '' || isset($prefixable[$source])) && preg_match('/^'.preg_quote($prefix.$source, '/').'(-|$)/', $text) === 1) {
                        $found = [$prefix.$source, $factors[$source] * $scale];

                        break 2;
                    }
                }
            }

            if ($found === null) {
                return null;
            }

            $factor *= $side === 0 ? $found[1] ** $power : $found[1] ** -$power;
            $text = ltrim(substr($text, strlen($found[0])), '-');
        }
    }

    return $factor;
};

// Unitary units whose CLDR id isn't their name hyphenated, keyed by symbol.
$renamed = [
    't' => 'tonne', 'ton' => 'ton', 'qtr' => 'quarter', 'm/s²' => 'meter-per-square-second', 'ft/s²' => 'foot-per-square-second',
    'psi' => 'pound-force-per-square-inch', 'lbf⋅ft' => 'pound-force-foot', 'lbf⋅in' => 'pound-force-inch', 'kgf' => 'kilogram-force',
    'BTU' => 'british-thermal-unit-it', 'BTU_th' => 'british-thermal-unit', 'cal_IT' => 'calorie-it', 'fl dr' => 'dram',
    'imp pt' => 'pint-imperial', 'imp cup' => 'cup-imperial', 'metric fl oz' => 'fluid-ounce-metric',
    'M' => 'mole-per-liter', 'mM' => 'millimole-per-liter', 'µM' => 'micromole-per-liter', 'nM' => 'nanomole-per-liter',
    'arcmin' => 'arc-minute', 'arcsec' => 'arc-second', '‰' => 'permille', '°' => 'degree', 'mmHg' => 'millimeter-ofhg', 'inHg' => 'inch-ofhg',
    'mpg (imp)' => 'mile-per-gallon-imperial', 'thm' => 'therm-us', 'c' => 'light-speed', 'g₀' => 'g-force',
    'tr' => 'revolution', 'Ω' => 'ohm', 'kΩ' => 'kiloohm', 'MΩ' => 'megaohm',
];

// Taken without the factor check. L/100km is a reciprocal, which the check can't
// compare. CLDR's own figures for the dessertspoons and the shaku (4/121 m, not
// 10/33) are wrong, but their names are right.
$trusted = [
    'L/100km' => 'liter-per-100-kilometer', 'ppm' => 'permillion', 'dsp' => 'dessert-spoon', 'imp dsp' => 'dessert-spoon-imperial',
    'shaku' => 'shaku-length', 'sun (JP)' => 'sun', 'ken (JP)' => 'ken', 'jō (JP)' => 'jo-jp', 'ri (JP)' => 'ri-jp',
    'tsubo' => 'bu-jp', 'se (JP)' => 'se-jp', 'chō' => 'cho', 'tō' => 'to-jp', 'koku' => 'koku', 'JP cup' => 'cup-jp', 'fun (JP)' => 'fun',
];

// CLDR's dram is a volume and its mil is not the thou.
$excluded = ['dr', 'mil'];

$candidates = [];

foreach (UnitRegistry::default()->all() as $unit) {
    if (! $unit instanceof MeasurementUnit || ! $unit instanceof BackedEnum || str_contains($unit::class, 'Novelty') || in_array($unit->symbol(), $excluded, true)) {
        continue;
    }

    $symbol = $unit->symbol();
    $words = explode(' ', str_replace(['-', ' squared'], [' ', ''], strtolower($unit->name())));
    $qualifiers = array_values(array_intersect($words, ['imperial', 'metric', 'troy', 'scandinavian']));
    $candidates[$symbol] = [$unit, $trusted[$symbol] ?? $renamed[$symbol] ?? implode('-', [...array_diff($words, $qualifiers), ...$qualifiers])];
}

// CLDR and Unitary pick different base units (revolutions vs radians), so compare
// ratios: within a dimension, every correct id has the same CLDR ÷ Unitary ratio.
$ratios = [];

foreach ($candidates as $symbol => [$unit, $id]) {
    $factor = isset($trusted[$symbol]) ? null : $factorOf($id);

    if ($factor !== null) {
        $ratios[$unit->dimension()->value][$symbol] = $factor / CompoundUnit::coherentFactorOf($unit)->toFloat();
    }
}

$near = fn (float $a, float $b): bool => abs($a - $b) <= 1e-5 * max(abs($a), abs($b));
$ids = [];

foreach ($candidates as $symbol => [$unit, $id]) {
    if (isset($trusted[$symbol])) {
        $ids[$symbol] = $id;

        continue;
    }

    $peers = $ratios[$unit->dimension()->value] ?? [];
    $ratio = $peers[$symbol] ?? null;

    if ($ratio === null) {
        continue;
    }

    $support = array_map(fn (float $r) => count(array_filter($peers, fn (float $other) => $near($r, $other))), $peers);
    $reference = $peers[array_search(max($support), $support, true)];

    if ($near($ratio, $reference)) {
        $ids[$symbol] = $id;
    } else {
        printf("  skipped %s: CLDR's %s has a different size\n", $symbol, $id);
    }
}

ksort($ids);
write_data('unit-ids.php', ['source' => $data['source'], 'retrieved' => $data['retrieved'], 'ids' => $ids]);
printf("unit-ids.php: %d of %d units have a CLDR id\n", count($ids), count($candidates));
