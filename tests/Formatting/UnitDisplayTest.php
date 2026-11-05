<?php

declare(strict_types=1);

use Unitary\Formatting\UnitNames;
use Unitary\Units\Length;
use Unitary\Units\Speed;
use Unitary\UnitDisplay;

use function Unitary\convert;

beforeEach(function () {
    if (! class_exists(MessageFormatter::class)) {
        $this->markTestSkipped('ext-intl is needed for CLDR unit names');
    }
});

it('writes units narrow, short or long, like Intl.NumberFormat', function (string $quantity, string $narrow, string $short, string $long) {
    $q = convert($quantity);

    expect($q->format(display: 'narrow'))->toBe($narrow)
        ->and($q->format(display: UnitDisplay::Short))->toBe($short)
        ->and($q->format(display: 'long'))->toBe($long);
})->with([
    ['2 in', '2″', '2 in', '2 inches'],
    ['1 in', '1″', '1 in', '1 inch'],
    ['80 km/h', '80km/h', '80 km/h', '80 kilometers per hour'],
    ['£1', '£1.00', '£1.00', '1.00 British pounds'],
    ['1 Gm', '1Gm', '1 Gm', '1 gigameter'],
]);

it('writes units in the locale\'s language, with its plural rules', function (string $quantity, string $locale, string $expected) {
    expect(str_replace(["\u{00A0}", "\u{202F}"], ' ', convert($quantity)->format(locale: $locale, display: 'long')))->toBe($expected);
})->with([
    ['2 in', 'fr', '2 pouces'],
    ['1.5 in', 'fr-FR', '1,5 pouce'],
    ['2 in', 'de', '2 Zoll'],
    ['5 in', 'pl', '5 cali'],
    ['1.5 in', 'pl', '1,5 cala'],
    ['80 km/h', 'fr', '80 kilomètres par heure'],
    ['1234567.891 m', 'fr', '1 234 567,891 mètres'],
    ['£1234.5', 'fr', '1 234,50 livres sterling'],
    ['20 °C', 'fr', '20 degrés Celsius'],
]);

it('keeps every digit of the exact value', function () {
    expect(convert('0.1234567890123456789 in')->format(locale: 'fr', display: 'long'))->toBe("0,1234567890123456789\u{00A0}pouce")
        ->and(convert('123456789012345678.5 m')->format(1, locale: 'en', display: 'short'))->toBe('123,456,789,012,345,678.5 m');
});

it("falls back to Unitary's own names for units CLDR doesn't have", function () {
    expect(convert('3 smoots')->format(locale: 'fr', display: 'long'))->toBe('3 smoots')
        ->and(convert('1 smoot')->format(display: 'long'))->toBe('1 smoot')
        ->and(convert('20 Δ°C')->format(display: 'short'))->toBe('20 Δ°C')
        ->and(convert('3 smoots')->format(display: 'narrow'))->toBe('3smoot');
});

it('writes breakdowns part by part', function () {
    $height = convert('180 cm')->breakdown('ft', 'in');

    expect($height->toString(0, 'fr', 'long'))->toBe("5 pieds 11\u{00A0}pouces")
        ->and($height->toString(0, display: 'long'))->toBe('5 feet 11 inches')
        ->and($height->toString(1, 'en', 'narrow'))->toBe('5′ 10.9″')
        ->and($height->toString(0))->toBe('5 ft 11 in');
});

it('leaves the default output alone', function () {
    expect(convert('2 in')->format(locale: 'fr'))->toBe('2 in')
        ->and(convert('1234567.891 m')->format(locale: 'de'))->toBe('1.234.567,891 m');
});

it('finds the CLDR id for named, prefixed and compound units', function () {
    expect(UnitNames::cldrId(Length::Inch))->toBe('inch')
        ->and(UnitNames::cldrId(Speed::KilometerPerHour))->toBe('kilometer-per-hour')
        ->and(UnitNames::cldrId(Length::Meter->withPrefix(Unitary\Prefix::Deca)))->toBe('dekameter')
        ->and(UnitNames::cldrId(convert('3 m')->multipliedBy(convert('2 m'))->dividedBy(convert('1 s'))->unit()))->toBe('square-meter-per-second')
        ->and(UnitNames::cldrId(convert('5 $/h')->unit()))->toBeNull();
});
