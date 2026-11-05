<?php

declare(strict_types=1);

use Unitary\Breakdown;
use Unitary\Dimension;
use Unitary\Preferences\UnitPreferences;

use function Unitary\convert;

it('picks the unit a region uses for a kind of value', function (string $quantity, string $locale, string $usage, string $expected) {
    expect((string) convert($quantity)->forLocale($locale, $usage))->toBe($expected);
})->with([
    ['80 km', 'de-DE', 'road', '80 km'],
    ['80 km', 'sv-SE', 'road', '8 scand mil'],
    ['5 km', 'sv-SE', 'road', '5 km'],
    ['180 cm', 'fr-FR', 'person-height', '1 m 80 cm'],
    ['180 cm', 'de-DE', 'person-height', '180 cm'],
    ['80 kg', 'de-DE', 'person', '80 kg'],
    ['20 °C', 'en-US', 'weather', '68 °F'],
    ['68 °F', 'en-GB', 'weather', '20 °C'],
    ['20 Δ°C', 'US', 'weather', '36 Δ°F'],
    ['10 L/100km', 'ja-JP', 'vehicle-fuel', '10 km/L'],
    ['5.5 mmol/L', 'en-US', 'blood-glucose', '99.085635 mg/dL'],
    ['1013 hPa', 'en-GB', 'baromtrc', '1013 mbar'],
    ['200 s', 'en', 'media', '3 min 20 s'],
    ['30 mo', 'en', 'person-age', '2.5 yr'],
    ['20 mo', 'en', 'person-age', '1 yr 8 mo'],
    ['1000 kcal', 'US', 'food', '1000 kcal'],
]);

it('rounds short road distances to CLDR increments', function (string $quantity, string $locale, string $expected) {
    expect((string) convert($quantity)->forLocale($locale, 'road'))->toBe($expected);
})->with([
    ['400 m', 'en-GB', '450 yd'],
    ['120 m', 'en-US', '400 ft'],
    ['0.6 km', 'de', '600 m'],
    ['0.012 km', 'de', '10 m'],
    ['7 m', 'de', '7 m'],
]);

it('returns mixed units as a breakdown', function () {
    $height = convert('180 cm')->forLocale('en-US', 'person-height');
    $weight = convert('80 kg')->forLocale('en-GB', 'person');

    expect($height)->toBeInstanceOf(Breakdown::class)
        ->and($height->toString(0))->toBe('5 ft 11 in')
        ->and($weight)->toBeInstanceOf(Breakdown::class)
        ->and($weight->toString(0))->toBe('12 st 8 lb');
});

it('converts fuel economy the right way round for each region', function () {
    expect(convert('10 L/100km')->forLocale('en-GB', 'vehicle-fuel')->format(1))->toBe('28.2 mpg (imp)')
        ->and(convert('10 L/100km')->forLocale('en-US', 'vehicle-fuel')->format(1))->toBe('23.5 mpg');
});

it('falls back to the dimension default, then the worldwide default', function () {
    expect((string) convert('5 kg')->forLocale('GB', 'no-such-usage')->format(2))->toBe('11.02 lb')
        ->and((string) convert('180 cm')->forLocale('GB', 'person-height-seated')->toString(0))->toBe('5 ft 11 in')
        ->and((string) convert('5 km')->forLocale('NZ', 'road'))->toBe('5 km')
        ->and((string) convert('5 V')->forLocale('US'))->toBe('5 V');
});

it('reads the region from a locale', function (string $locale, string $region) {
    expect(UnitPreferences::regionOf($locale))->toBe($region);
})->with([
    ['en-GB', 'GB'],
    ['en_gb', 'GB'],
    ['GB', 'GB'],
    ['zh-Hant-TW', 'TW'],
    ['es-419', '419'],
    ['en', '001'],
    ['se', '001'],
]);

it('lists the usages for a dimension', function () {
    expect(UnitPreferences::usages(Dimension::Length))->toContain('road', 'person-height', 'rainfall')
        ->and(UnitPreferences::usages(Dimension::Time))->toContain('media', 'person-age')
        ->and(UnitPreferences::usages(Dimension::Voltage))->toBe([]);
});
