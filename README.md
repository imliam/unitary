# Unitary

- [Introduction](#introduction)
- [Installation](#installation)
- [Converting Values](#converting-values)
    - [What You Can Type](#what-you-can-type)
    - [Ambiguous Input](#ambiguous-input)
    - [Validating Input](#validating-input)
- [Working With Quantities](#working-with-quantities)
    - [Reading Values](#reading-values)
    - [Arithmetic](#arithmetic)
    - [Temperatures](#temperatures)
    - [Readable Output](#readable-output)
    - [Unit Names and Languages](#unit-names-and-languages)
    - [Regional Units](#regional-units)
    - [Uncertainty and Significant Figures](#uncertainty-and-significant-figures)
- [Units](#units)
- [Currencies](#currencies)
    - [Historical Rates](#historical-rates)
    - [Inflation](#inflation)
- [The Calculator](#the-calculator)
- [Colors](#colors)
- [Time Zones](#time-zones)
- [Coordinates and Distances](#coordinates-and-distances)
- [Sizes and Scales](#sizes-and-scales)
- [Ingredients and Fuels](#ingredients-and-fuels)
- [PHPStan](#phpstan)
- [Laravel](#laravel)
- [The Command Line](#the-command-line)
- [Custom Units](#custom-units)
- [Errors](#errors)
- [Development](#development)

## Introduction

Unitary converts between units in PHP. Give it a value and a unit, or just a sentence, and it gives you back an exact answer:

```php
use function Unitary\convert;

convert('5 ft 11 in to cm');            // 180.34 cm
convert('£23 in USD')->format();        // $30.56
convert('two and a half miles in km');  // 4.02336 km
convert('1 cup of flour in g');         // 120 g
convert('UK 9 shoe in EU');             // EU 43
convert('9am PST in Tokyo');            // 2026-10-02T01:00:00+09:00[Asia/Tokyo]
```

Unitary stores every value as an exact fraction, so conversions never lose precision. Converting 1,000 miles to nanometres gives exactly `1609344000000000 nm`, and converting back gives exactly 1,000 miles.

Out of the box, Unitary knows about:

- 300+ units across 41 dimensions, including every SI prefix, imperial and US units, data sizes and cooking measures
- All 159 ISO 4217 currencies, with live and historical exchange rates
- Inflation, colors, time zones, coordinates, and shoe, ring and paper sizes

It also ships with a calculator, a PHPStan extension, Laravel integration and a command line tool.

## Installation

You may install Unitary using Composer:

```bash
composer require imliam/unitary
```

## Converting Values

The `convert` function is the main way to use Unitary. You may pass it a number and a unit, then call `to` with the unit you want:

```php
use Unitary\Units\{Length, Temperature};
use function Unitary\convert;

convert(5, Length::Kilometer)->to(Length::Mile);

convert(100, Temperature::Celsius)->to('°F');   // 212 °F
```

Units may be given as enum cases or as strings. These all mean the same thing:

```php
use Unitary\Currency\Currency;

convert(23, Currency::GBP);
convert(23, 'gbp');
convert('£23');
convert('23 GBP');
```

You may also write the whole conversion as a single string:

```php
convert('5 ft 11 in to cm');   // 180.34 cm
convert('65% of 30');          // 19.5
convert('50 + 4');             // 54
```

### What You Can Type

Unitary reads input the way a person would write it. The target unit may follow `to`, `in`, `into`, `as`, `->`, `→` or `=`.

Numbers may be written in most common forms:

```php
convert('1,234.5 m');
convert('1.5e-9 s');
convert('2½ cups');
convert('twenty-three quid');
convert('£3.5k');
convert('two and a half miles in km');
```

Amounts may combine several units:

```php
convert("13' 11\" in m");   // 4.2418 m
convert('1h 30m in min');
convert('2 lb, 3 oz in g');
```

Units may be spelled however you like. `km`, `KM`, `kilometres` and `kilometers` all work, as do `m²`, `m2` and `sq m`. Any SI unit accepts a prefix, so `Gm`, `MeV`, `TWh` and `kibibytes` are all understood.

Compound units and rates work too:

```php
convert('$20/day + $300/week in /month');   // 1913.175 USD/mo
convert('€30/day in €/month');
convert('80 kmph in m/s');
```

### Other Languages

`Quantity::parse` accepts a locale, so you may read quantities written the way people write them in that language:

```php
Quantity::parse('1,5 kg', 'de');                // 1.5 kg
Quantity::parse('5 Fuß und 10 Zoll', 'de');     // 70 in
Quantity::parse('1 234,5 mètres', 'fr');        // 1234.5 m
Quantity::parse('178 сантиметров', 'ru');       // 178 cm
Quantity::parse('١٢٫٥ كيلوغرام', 'ar');          // 12.5 kg
```

Numbers may also be written as words in the locale's language, along with common idioms for halves, quarters and dozens:

```php
Quantity::parse('zweitausendeins Meter', 'de');   // 2001 m
Quantity::parse('dreieinhalb Kilogramm', 'de');   // 3.5 kg
Quantity::parse('deux heures et demie', 'fr');    // 2.5 h
Quantity::parse('una docena de metros', 'es');    // 12 m
Quantity::parse('五英尺', 'zh');                   // 5 ft
```

Number words are read with ICU's spell-out rules, which cover every language CLDR does. Idioms are covered for German, French, Spanish, Italian, Portuguese and Dutch, and English ones like "a dozen" and "half a" work in any locale.

Numbers may use the locale's separators and digits. A number only counts as the locale's when it fits the locale's pattern, so `1.5` still means one and a half in German, while `1.234,5` means one thousand two hundred and thirty-four and a half. Units may be named in the locale's language, in any of the plural forms and grammatical cases CLDR has, and parts may be joined with its word for "and". English input still works.

> [!NOTE]
> Unit names and number words in other languages need the `intl` extension. Without it, numbers are still read the locale's way for common locales, but units must be written in English.

### Ambiguous Input

Some text could mean more than one thing. Unitary picks the most common meaning and uses the rest of the conversion to break ties:

```php
convert(2, 'h')->to('m');          // 120 min, because "m" means minutes next to hours
convert('23 pounds in kg');        // 10.43262451 kg
convert('23 pounds in dollars');   // GBP to USD, because the target is a currency
convert('12 AWG in mm');           // a wire gauge, not 12 Aruban florins
```

### Validating Input

If you're accepting input from users, you may check whether Unitary understands it first using the `understands` method:

```php
Unitary::understands('5 ft 11 in');   // true
Unitary::understands('5 florbs');     // false
Unitary::understands('5 kg to m');    // false
```

The `understands` method never touches the network. It checks currencies against placeholder rates, even when you've configured a live provider.

Alternatively, the `tryConvert` method returns `null` instead of throwing an exception:

```php
Unitary::tryConvert('5 florbs');   // null
```

## Working With Quantities

Every conversion returns a `Quantity`. You may also create one directly:

```php
use Unitary\Quantity;

Quantity::of(5, 'kg');
Quantity::parse('5 ft 11 in');
```

### Reading Values

A quantity holds its value as an exact fraction. You may read it in whichever form you need:

```php
$q = convert(1, '°F')->to('°C');

$q->value();       // BigRational -155/9
$q->toDecimal();   // -17.222222222222222222
$q->toDecimal(2);  // -17.22
$q->format(1);     // "-17.2 °C"
$q->unitName();    // "celsius"
```

The `format` method adds digit grouping, and you may pass a locale:

```php
convert('1234567.891 m')->format();                // "1,234,567.891 m"
convert('1234567.891 m')->format(locale: 'de');    // "1.234.567,891 m"
```

Quantities may also be converted to JSON:

```php
json_encode(convert('£23'));
// {"value":"23","unit":"GBP","unitName":"Pound sterling","dimension":"currency"}
```

### Arithmetic

You may add, subtract, multiply and divide quantities. Unitary converts the units for you:

```php
Quantity::of(1, 'm')->plus(Quantity::of(50, 'cm'));   // 1.5 m
```

Multiplying or dividing quantities creates new units, just like on paper:

```php
Quantity::of(90, 'km')->dividedBy(Quantity::of(3, 'h'));     // 30 km/h
Quantity::of(3, 'm')->multipliedBy(Quantity::of(4, 'ft'));   // 3.6576 m²
Quantity::of(100, 'J')->dividedBy(Quantity::of(2, 's'));     // 50 W
Quantity::of(3, 'm')->power(3);                              // 27 m³
```

This means a rate multiplied by a time gives you a total:

```php
convert('$120/hr')->multipliedBy(Quantity::of(30, 'min'));   // 60 USD
```

The `simplify` method converts a quantity to SI base units:

```php
Quantity::of(36, 'km/h')->simplify();   // 10 m/s
```

### Temperatures

Subtracting one temperature from another gives you a temperature *difference*. Differences convert without the offset, so a change of 20 °C is a change of 36 °F:

```php
convert('30 °C - 10 °C in °F');   // 36 Δ°F, not 68 °F
```

When you add a temperature to another, Unitary treats the second one as a difference:

```php
convert('20 °C + 5 °F');   // 22.78 °C
```

### Readable Output

The `humanize` method picks the most readable prefix:

```php
convert('0.00002 m')->humanize();   // 20 µm
convert('12345 W')->humanize();     // 12.345 kW
```

The `toImperial` and `toMetric` methods switch between systems:

```php
convert('1.83 m')->toImperial()->format(2);   // 6.00 ft
convert('70 °F')->toMetric()->format(1);      // 21.1 °C
```

The `breakdown` method splits a value across several units:

```php
convert('100000 s')->breakdown();                        // 1 d 3 h 46 min 40 s
convert('1.80 m')->breakdown('ft', 'in')->toString(0);   // 5 ft 11 in
```

### Unit Names and Languages

By default, `format` writes the unit's symbol. You may ask for the full name instead using the `display` argument:

```php
convert('2 in')->format(display: 'narrow');   // 2″
convert('2 in')->format(display: 'short');    // 2 in
convert('2 in')->format(display: 'long');     // 2 inches
```

Add a locale and Unitary writes the unit in that language, with the right plural form:

```php
convert('2 in')->format(locale: 'fr', display: 'long');      // 2 pouces
convert('1.5 in')->format(locale: 'pl', display: 'long');    // 1,5 cala
convert('80 km/h')->format(locale: 'fr', display: 'long');   // 80 kilomètres par heure
```

> [!NOTE]
> Translated unit names need the `intl` extension. Without it, or for units that don't have a translation (like smoots), Unitary uses English names.

### Regional Units

Different countries measure the same thing in different units. A road in the UK is measured in miles, but a person's height in the US is measured in feet and inches. The `forLocale` method picks the right unit for a region and a kind of value:

```php
convert('80 km')->forLocale('en-GB', 'road')->format(0);              // 50 mi
convert('180 cm')->forLocale('en-US', 'person-height')->toString(0);  // 5 ft 11 in
convert('80 kg')->forLocale('en-GB', 'person')->toString(0);          // 12 st 8 lb
convert('10 L/100km')->forLocale('en-GB', 'vehicle-fuel')->format(1); // 28.2 mpg (imp)
```

You may pass a locale like `en-GB`. A language on its own, like `en`, gets the worldwide default, so pass `en-US` rather than `en`.

To see which kinds of value are available for a dimension, use `UnitPreferences::usages(Dimension::Length)`. This data comes from [CLDR](https://github.com/unicode-org/cldr/blob/main/common/supplemental/units.xml).

### Uncertainty and Significant Figures

You may give a value a margin of error. Unitary carries it through conversions and arithmetic:

```php
convert('5.0 ± 0.1 m in ft');                                   // 16.40 ± 0.33 ft
convert('2.0 ± 0.1 m')->multipliedBy(convert('3.0 ± 0.2 m'));   // 6.00 ± 0.70 m²
```

The margins are worst case. Unitary also remembers how many significant figures you wrote, and the `toPrecision` method rounds to match:

```php
convert('2.50 kg in lb')->toPrecision();   // 5.51 lb
```

## Units

Each dimension is an enum, such as `Length`, `Mass`, `Time`, `Temperature`, `Speed`, `Energy` or `DigitalStorage`. There are 41 in total, all in the `Unitary\Units` namespace.

You may add an SI prefix to any SI unit:

```php
convert(1, Length::Meter->withPrefix(Prefix::Giga))->to('km');   // 1000000 km
```

For fun, there are novelty units like bananas, football pitches, double-decker buses and Olympic pools. Each one cites the source of its value:

```php
convert('1 Burj Khalifa in bananas')->format(0);   // 4,657 banana
```

Some unit names mean different things in different places. Unitary follows these rules:

- `gallon`, `cup` and `fl oz` are US units. Write `imp gal` or `UK pint` for imperial.
- `ton` is the US short ton. `t`, `tonne` and `metric ton` are 1,000 kg.
- `kB` is 1,000 bytes. `KiB` is 1,024 bytes.
- `tbsp` and `tsp` are US spoons. Write `AU tbsp` for the 20 ml Australian tablespoon.
- `carat` is a mass (0.2 g). `karat` is gold purity.
- `mil` is a thousandth of an inch. Write `scand mil` for the 10 km Scandinavian mile.

## Currencies

Unitary won't make network requests until you choose an exchange rate provider:

```php
use Unitary\Currency\Providers\EuropeanCentralBank;

Unitary::useExchangeRates(new EuropeanCentralBank());

convert('£23 in USD')->format();   // $30.56
```

The following providers are available:

| Provider | Currencies | API key |
|---|---|---|
| `EuropeanCentralBank` | ~30, with history back to 1999 | Not needed |
| `Frankfurter` | ~30 (ECB data), self-hostable | Not needed |
| `ExchangeRateApi` | ~160 | Optional |
| `OpenExchangeRates` | ~170 | Required |
| `BigMacIndex` | Purchasing-power parity from The Economist | Not needed |

You may combine providers with `ChainedExchangeRates`. Unitary tries each one in turn:

```php
Unitary::useExchangeRates(new ChainedExchangeRates(
    new EuropeanCentralBank(),
    new ExchangeRateApi(),
));
```

To avoid fetching rates on every request, pass a cache. `Psr16RateCache` wraps any PSR-16 cache:

```php
new EuropeanCentralBank(cache: new Psr16RateCache($cache), ttl: 3600);
```

### Historical Rates

You may convert using the rate from a past date. If that date wasn't a business day, Unitary uses the business day before it:

```php
convert('£23')->to('USD', at: new DateTimeImmutable('2020-03-01'));

convert('£23 in USD on 2020-03-01');
```

### Inflation

Unitary includes price index data for the US (from 1913) and the UK (from 1800), so you may adjust for inflation without a network connection:

```php
convert('$100 in 1970 in today\'s money');
convert('$100 in 1950 in 2020 dollars');

Inflation::adjust(Quantity::of(100, 'GBP'), from: 1970, to: 2000);
```

## The Calculator

The `evaluate` method works like a notepad calculator. It ignores words it doesn't recognise, so you may write calculations the way you'd say them:

```php
Unitary::evaluate('3 pizzas at $12 each + 20% tip');             // 43.2 USD
Unitary::evaluate('(5 ft + 30 cm) × 2 in m');                    // 3.648 m
Unitary::evaluate('1/2 cup of flour + 2 tbsp of butter in g');   // 88.25 g
Unitary::evaluate('$55 - 10%');                                  // 49.5 USD
Unitary::evaluate('50 is what % of 200');                        // 25%
```

The calculator supports `+ − × ÷ ^`, parentheses, and the functions `round`, `floor`, `ceil`, `abs`, `min`, `max` and `sqrt`.

If you need variables, the `calculator` method returns a sheet that remembers them between lines:

```php
$sheet = Unitary::calculator();

$sheet->evaluate('rate = $120/hr');
$sheet->evaluate('42 hr × rate');   // 5040 USD
```

A sheet also understands `prev` for the last result and `sum` for the total so far:

```php
$sheet->evaluateSheet("10 m\n20 m\nsum");   // …, 30 m
```

## Colors

You may parse a color in any CSS format and convert it to another color space:

```php
use Unitary\Color\{Color, ColorSpace};

$red = Color::parse('#ff0000');

$red->to(ColorSpace::Oklch);   // oklch(62.8% 0.2577 29.23)
$red->toHsl()->lightness;      // 50.0
```

Unitary supports 14 color spaces, including RGB, HSL, Lab, OKLCH, Display P3 and CMYK. The maths follows CSS Color 4.

## Time Zones

You may convert times between zones using city names, zone names or abbreviations:

```php
convert('10am EST in Tokyo');
convert('2026-10-01 10:00 Europe/London in America/New_York');

Timezone::resolve('new york');   // America/New_York
Timezone::resolve('BST');        // Europe/London
```

## Coordinates and Distances

You may convert coordinates between formats, or measure the distance between two points:

```php
convert('51°30′26″N 0°7′39″W to decimal');                  // 51.507222, -0.1275
convert('51.5072, -0.1276 to 48.8566, 2.3522 in miles');    // ≈ 213.69 mi
```

The `Coordinate` class converts to UTM, MGRS, geohash and plus codes:

```php
use Unitary\Geo\Coordinate;

$london = Coordinate::parse('51.5072, -0.1276');

$london->toUtm();
$london->toGeohash();
$london->distanceTo(Coordinate::parse('48.8566, 2.3522'))->to('km');
```

## Sizes and Scales

Unitary converts between sizing systems and scales:

```php
convert('UK 9 shoe in EU');      // EU 43
convert('ring size N to US');    // US 6.75
convert('A4 in inches');         // 8.27 × 11.69 in
convert('gas mark 4 in °F');     // 350 °F
convert('20 mph in beaufort');   // Beaufort 5 (Fresh breeze)
```

These follow published standards, such as ISO 216 for paper, rather than any one shop's size chart.

## Ingredients and Fuels

You may convert between volume and mass for common ingredients, and between fuel and energy:

```php
convert('1 cup of flour in g');          // 120 g
convert('2 cups sugar to grams');        // 396 g
convert('1 gal of petrol in kWh');       // 33.705 kWh
convert('10 gal of diesel in lb CO2');   // 224.43… lb
```

Cooking densities come from King Arthur Baking and the USGS. Fuel figures come from the EPA and the US Department of Energy.

## PHPStan

Unitary includes a PHPStan extension that catches unit mistakes before your code runs:

```php
$distance = convert('5 km');

$distance->plus(convert('3 kg'));   // Error: Cannot add mass (kg) to length (km).
$distance->to('kg');                // Error: Cannot convert length (km) to mass (kg).
convert('5 florbs');                // Error: Unable to parse "5 florbs"
```

It also knows the unit of each quantity, so dividing `5 km` by `2 h` gives you a `Quantity<Speed::KilometerPerHour>`.

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), the extension is registered for you. Otherwise, add it to your `phpstan.neon` file:

```neon
includes:
    - vendor/imliam/unitary/extension.neon
```

## Laravel

Unitary's service provider is registered automatically. To choose an exchange rate provider and cache store, publish the config file:

```bash
php artisan vendor:publish --tag=unitary-config
```

### Casts

You may store quantities on your Eloquent models using the `AsQuantity` cast:

```php
use Unitary\Laravel\Casts\AsQuantity;

protected function casts(): array
{
    return [
        'weight' => AsQuantity::of(Mass::Kilogram),
        'height' => AsQuantity::withUnit(Dimension::Length),
    ];
}
```

`AsQuantity::of` stores the value in a single column in a fixed unit. `AsQuantity::withUnit` uses two columns, `height_value` and `height_unit`, so each row may keep its original unit.

You may then query and sort by those columns:

```php
Parcel::query()
    ->whereQuantity('height', '>', '5 ft')
    ->orderByQuantity('height')
    ->get();
```

### Validation

Unitary adds validation rules for quantities, units and currencies:

```php
$request->validate([
    'weight' => 'quantity:mass,min:1g,max:30kg',
    'unit' => 'unit:length',
    'currency' => 'currency',
]);

$weight = $request->quantity('weight', dimension: Dimension::Mass);
```

### Input Locale

The `quantity` rule and `$request->quantity()` read input the way your app's locale writes it. In a German app, `1,5 kg` is one and a half kilograms and `5 Fuß 10 Zoll` is 70 inches. To read input in another locale, set `UNITARY_LOCALE` in your `.env` file, or call `locale` on a rule:

```php
QuantityRule::of(Dimension::Mass)->max('30 kg')->locale('en');
```

Anything written in code, like a rule's limits, a default value or a value set on a cast, is read the English way.

### Blade

You may format a quantity in your Blade templates using the `@quantity` directive:

```blade
@quantity($parcel->weight, 2)
```

Pass a `usage` to show the units your user's region uses. For example, this shows miles in the UK and kilometres in Germany:

```blade
@quantity($trip->distance, 0, usage: 'road')
```

The region comes from the app locale. If your locale is a bare language like `en`, set `UNITARY_REGION` in your `.env` file.

## The Command Line

Unitary includes a command line tool:

```bash
vendor/bin/unitary "5 ft 11 in to cm"                  # 180.34 cm
vendor/bin/unitary "£23 in USD" --rates=frankfurter    # $30.56
vendor/bin/unitary "1 cup of flour in g" --json
vendor/bin/unitary eval budget.txt
```

To list the units in a dimension, use the `units` command:

```bash
vendor/bin/unitary units length
```

You may check whether input is understood with the `check` command. It exits with `0` if it is and `1` if it isn't:

```bash
vendor/bin/unitary check "5 florbs" || echo "nope"
```

Exchange rates are cached on disk for an hour. You may set the provider with the `UNITARY_RATES` environment variable.

## Custom Units

You may define your own units as an enum that implements `MeasurementUnit` and uses the `IsMeasurementUnit` trait. Any enum in `src/Units` shows the pattern.

To use them, register them with a `UnitRegistry` and create a `Converter`:

```php
$registry = (new UnitRegistry())->register(...MyUnits::cases());

$converter = new Converter($registry, new FixedExchangeRates('EUR', ['GBP' => '0.85']));

$converter->convert('3 smoots to m');
```

## Errors

Every exception Unitary throws implements `Unitary\Exceptions\UnitaryException`, so you may catch them all at once:

```php
use Unitary\Exceptions\UnitaryException;

try {
    convert($input);
} catch (UnitaryException $e) {
    // ...
}
```

The most common exceptions are `ParseException`, `UnknownUnitException` and `IncompatibleUnitsException`.

## Development

```bash
composer test      # Run the test suite
composer analyse   # Run PHPStan
```
