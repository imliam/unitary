<?php

declare(strict_types=1);

use Unitary\Exceptions\ParseException;
use Unitary\Parsing\LocalizedNumbers;
use Unitary\Parsing\LocalizedNumberWords;
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

it('reads numbers written as words in the locale’s language', function (string $input, string $locale, string $expected) {
    expect(Quantity::parse($input, $locale)->format())->toBe($expected);
})->with([
    'German' => ['zwei Meter', 'de', '2 m'],
    'a German compound number' => ['zweitausendeins Meter', 'de', '2,001 m'],
    'German feet and inches in words' => ['fünf Fuß und zehn Zoll', 'de', '70 in'],
    'French' => ['quatre-vingt-dix-neuf mètres', 'fr', '99 m'],
    'Spanish' => ['dos mil uno metros', 'es', '2,001 m'],
    'Italian' => ['tre chilogrammi', 'it', '3 kg'],
    'Russian' => ['две тысячи один метр', 'ru', '2,001 m'],
    'Chinese, written without a space' => ['五英尺', 'zh', '5 ft'],
    'Japanese, written without a space' => ['二千一メートル', 'ja', '2,001 m'],
]);

it('reads the locale’s idioms for halves, quarters and dozens', function (string $input, string $locale, string $expected) {
    expect(Quantity::parse($input, $locale)->format(2))->toBe($expected);
})->with([
    ['dreieinhalb Kilogramm', 'de', '3.50 kg'],
    ['eineinhalb Liter', 'de', '1.50 L'],
    ['anderthalb Stunden', 'de', '1.50 h'],
    ['eine halbe Stunde', 'de', '0.50 h'],
    ['dreiviertel Liter', 'de', '0.75 L'],
    ['zwei Dutzend kg', 'de', '24.00 kg'],
    ['deux heures et demie', 'fr', '2.50 h'],
    ['un demi-litre', 'fr', '0.50 L'],
    ['trois quarts d’heure', 'fr', '0.75 h'],
    ['une douzaine de mètres', 'fr', '12.00 m'],
    ['dos horas y media', 'es', '2.50 h'],
    ['media hora', 'es', '0.50 h'],
    ['una docena de metros', 'es', '12.00 m'],
    ['due ore e mezza', 'it', '2.50 h'],
    ['duas horas e meia', 'pt', '2.50 h'],
    ['tweeënhalf uur', 'nl', '2.50 h'],
    ['anderhalf uur', 'nl', '1.50 h'],
]);

it('still reads English number words with a locale', function () {
    expect(Quantity::parse('a dozen kg', 'de')->format())->toBe('12 kg')
        ->and(Quantity::parse('two and a half miles', 'fr')->format(1))->toBe('2.5 mi');
});

it('doesn’t read a number word straight after a numeral, where it’s a unit', function () {
    // In French, "mille" is a thousand, and "milles" is part of some mile names.
    expect(fn () => Quantity::parse('5 milles', 'fr'))->toThrow(ParseException::class);
});

it('doesn’t read the start of a longer word as a number', function () {
    // "Dreieck" (triangle) starts with "drei" (three).
    expect(LocalizedNumberWords::normalize('ein Dreieck', 'de'))->toBe('1 Dreieck')
        ->and(LocalizedNumberWords::normalize('Dreieck', 'de'))->toBe('Dreieck');
});

it('reads the locale’s numerals before its number words, so they aren’t read twice', function () {
    expect(Quantity::parse('1.255 kg', 'de')->format())->toBe('1,255 kg')
        ->and(Quantity::parse('3,5 kg', 'de')->format())->toBe('3.5 kg');
});
