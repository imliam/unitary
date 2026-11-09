<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Parsing\LocalizedNumbers;
use Unitary\Parsing\LocalizedUnitNames;
use Unitary\Quantity;

it('reads quantities written the way a locale writes them', function (string $input, string $locale, string $expected) {
    expect(Quantity::parse($input, $locale)->format())->toBe($expected);
})->with([
    'German decimal comma' => ['1,5 kg', 'de', '1.5 kg'],
    'German grouping' => ['1.234,5 m', 'de', '1,234.5 m'],
    'German unit names' => ['1,5 Kilogramm', 'de', '1.5 kg'],
    'German feet and inches' => ['5 Fuß 10 Zoll', 'de', '70 in'],
    'German "and"' => ['5 Fuß und 10 Zoll', 'de', '70 in'],
    'German abbreviations with full stops' => ['2 Std. 30 Min.', 'de', '150 min'],
    'German temperature' => ['21 Grad Celsius', 'de', '21 °C'],
    'French grouping with a narrow space' => ["1\u{202F}234,5 mètres", 'fr', '1,234.5 m'],
    'French "and"' => ['5 pieds et 10 pouces', 'fr', '70 in'],
    'Swiss German grouping' => ['1’234.5 m', 'de_CH', '1,234.5 m'],
    'Polish plural forms' => ['178 centymetrów', 'pl', '178 cm'],
    'Polish grammatical case' => ['2,5 litra', 'pl', '2.5 L'],
    'Russian' => ['5 футов 10 дюймов', 'ru', '70 in'],
    'Russian abbreviation' => ['178 см', 'ru', '178 cm'],
    'Spanish' => ['5 pies 10 pulgadas', 'es', '70 in'],
    'Arabic digits and separators' => ['١٢٫٥ كيلوغرام', 'ar', '12.5 kg'],
    'Japanese' => ['5 フィート', 'ja', '5 ft'],
    'Chinese' => ['178 厘米', 'zh', '178 cm'],
    'a locale written with a hyphen' => ['1,5 kg', 'de-AT', '1.5 kg'],
]);

it('still reads English with a locale', function (string $input, string $locale, string $expected) {
    expect(Quantity::parse($input, $locale)->format())->toBe($expected);
})->with([
    ['5 ft 10 in', 'de', '70 in'],
    ['1.5 kg', 'de', '1.5 kg'],
    ['2 lb, 3 oz', 'de', '35 oz'],
]);

it('reads numbers the locale’s way only when they fit its pattern', function () {
    expect(Quantity::parse('1,234 kg', 'de')->format(3))->toBe('1.234 kg')
        ->and(Quantity::parse('1,234 kg')->format())->toBe('1,234 kg')
        ->and(Quantity::parse('1.5 kg', 'de')->format())->toBe('1.5 kg');
});

it('reads numbers only once, so an uncertainty isn’t read twice', function () {
    expect(Quantity::parse('5,0 ± 0,1 m', 'de')->format())->toBe('5 ± 0.10 m');
});

it('leaves ambiguous short names to the English rules', function () {
    expect(Quantity::parse('5 m', 'de')->format())->toBe('5 m')
        ->and(Quantity::parse('2 h 30 m', 'de')->format())->toBe('150 min');
});

it('doesn’t read the locale’s numbers without a locale', function () {
    Quantity::parse('5 Fuß');
})->throws(ParseException::class);

it('rewrites a locale’s numbers the English way', function (string $text, string $locale, string $expected) {
    expect(LocalizedNumbers::normalize($text, $locale))->toBe($expected);
})->with([
    ['1.234,5 kg', 'de', '1234.5 kg'],
    ['1,5 kg', 'de', '1.5 kg'],
    ['1.5 kg', 'de', '1.5 kg'],
    ['2 lb, 3 oz', 'de', '2 lb, 3 oz'],
    ['1 234,5 m', 'fr', '1234.5 m'],
    ['1’234.5 m', 'de_CH', '1234.5 m'],
    ['12,34,567 m', 'en_IN', '1234567 m'],
    ['1,234.5 m', 'en', '1,234.5 m'],
    ['2 1/2 cups', 'fr', '2 1/2 cups'],
    ['١٢٫٥', 'ar', '12.5'],
    ['१२३', 'hi', '123'],
]);

it('knows unit names in other languages', function (string $name, string $locale, ?string $symbol) {
    expect(LocalizedUnitNames::symbolFor($name, $locale))->toBe($symbol);
})->with([
    ['Fuß', 'de', 'ft'],
    ['fuß', 'de', 'ft'],
    ['Meilen', 'de', 'mi'],
    ['pieds', 'fr', 'ft'],
    ['stopach', 'pl', 'ft'],
    ['сантиметров', 'ru', 'cm'],
    ['ポンド', 'ja', 'lb'],
    'a name that means two units' => ['m', 'de', null],
    'not a unit' => ['Banane', 'de', null],
]);

it('knows the locale’s word for "and"', function () {
    expect(LocalizedUnitNames::conjunctions('de'))->toBe(['und'])
        ->and(LocalizedUnitNames::conjunctions('fr'))->toBe(['et'])
        ->and(LocalizedUnitNames::conjunctions('ja'))->toBe([]);
});
