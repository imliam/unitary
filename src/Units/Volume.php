<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: cubic meter. Unqualified gallon, cup, tablespoon etc. mean the US customary units.
 */
enum Volume: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case CubicMeter = 'm³';
    case CubicKilometer = 'km³';
    case CubicCentimeter = 'cm³';
    case CubicMillimeter = 'mm³';
    case Liter = 'L';
    case Milliliter = 'mL';
    case Centiliter = 'cL';
    case Deciliter = 'dL';
    case Hectoliter = 'hL';
    case CubicInch = 'in³';
    case CubicFoot = 'ft³';
    case CubicYard = 'yd³';
    case AcreFoot = 'ac⋅ft';
    case UsGallon = 'gal';
    case UsQuart = 'qt';
    case UsPint = 'pint';
    case UsCup = 'cup';
    case UsFluidOunce = 'fl oz';
    case Tablespoon = 'tbsp';
    case Teaspoon = 'tsp';
    case UsGill = 'gi';
    case UsFluidDram = 'fl dr';
    case Drop = 'gtt';
    case Pinch = 'pinch';
    case ImperialGallon = 'imp gal';
    case ImperialQuart = 'imp qt';
    case ImperialPint = 'imp pt';
    case ImperialFluidOunce = 'imp fl oz';
    case ImperialGill = 'imp gi';
    case ImperialTablespoon = 'imp tbsp';
    case ImperialTeaspoon = 'imp tsp';
    case ImperialDessertspoon = 'imp dsp';
    case ImperialCup = 'imp cup';
    case MetricCup = 'metric cup';
    case MetricTablespoon = 'metric tbsp';
    case MetricTeaspoon = 'metric tsp';
    case MetricPint = 'metric pt';
    case MetricFluidOunce = 'metric fl oz';
    case Dessertspoon = 'dsp';
    case AustralianTablespoon = 'AU tbsp';
    case JapaneseCup = 'JP cup';
    case Go = 'gō';
    case Sho = 'shō';
    case To = 'tō';
    case Koku = 'koku';
    case Barrel = 'bbl';
    case UsBushel = 'bu';

    public function dimension(): Dimension
    {
        return Dimension::Volume;
    }

    protected function define(): U
    {
        return match ($this) {
            self::CubicMeter => U::of('cubic meter')->aliases('m3', 'm^3', 'cu m'),
            self::CubicKilometer => U::of('cubic kilometer', '10^9')->aliases('km3', 'km^3'),
            self::CubicCentimeter => U::of('cubic centimeter', '10^-6')->aliases('cm3', 'cm^3', 'cc', 'ccm'),
            self::CubicMillimeter => U::of('cubic millimeter', '10^-9')->aliases('mm3', 'mm^3'),
            self::Liter => U::of('liter', '0.001')->prefixable()->aliases('l', 'ℓ', 'ltr', 'ltrs'),
            self::Milliliter => U::of('milliliter', '10^-6')->aliases('ml'),
            self::Centiliter => U::of('centiliter', '10^-5')->aliases('cl'),
            self::Deciliter => U::of('deciliter', '10^-4')->aliases('dl'),
            self::Hectoliter => U::of('hectoliter', '0.1')->aliases('hl'),
            self::CubicInch => U::of('cubic inch', '0.000016387064')->aliases('in3', 'in^3', 'cu in', 'cid'),
            self::CubicFoot => U::of('cubic foot', '0.028316846592')->aliases('ft3', 'ft^3', 'cu ft', 'cf'),
            self::CubicYard => U::of('cubic yard', '0.764554857984')->aliases('yd3', 'yd^3', 'cu yd'),
            self::AcreFoot => U::of('acre-foot', '1233.48183754752')->aliases('acre foot', 'acre feet', 'ac ft', 'ac-ft')->plural('acre-feet'),
            self::UsGallon => U::of('gallon', '0.003785411784')->aliases('US gallon', 'US gallons', 'gals'),
            self::UsQuart => U::of('quart', '0.000946352946')->aliases('US quart', 'US quarts'),
            self::UsPint => U::of('pint', '0.000473176473')->aliases('pt', 'US pint', 'US pints'),
            self::UsCup => U::of('cup', '0.0002365882365')->aliases('US cup', 'US cups'),
            self::UsFluidOunce => U::of('fluid ounce', '0.0000295735295625')->aliases('floz', 'fl. oz.', 'fl.oz', 'US fluid ounce', 'US fluid ounces', 'oz fl'),
            self::Tablespoon => U::of('tablespoon', '0.00001478676478125')->aliases('Tbsp', 'tbs', 'tbl', 'US tablespoon'),
            self::Teaspoon => U::of('teaspoon', '0.00000492892159375')->aliases('US teaspoon'),
            self::UsGill => U::of('gill', '0.00011829411825')->aliases('US gill', 'US gills'),
            self::UsFluidDram => U::of('fluid dram', '0.0000036966911953125')->aliases('US fluid dram', 'fluid drachm', 'fl dram', 'fʒ'),
            // Kitchen conventions rather than measurements: a drop is 1/576 and a pinch 1/128 of a US fluid ounce.
            self::Drop => U::of('drop', '0.0000295735295625 / 576')->aliases('gtts'),
            self::Pinch => U::of('pinch', '0.0000295735295625 / 128')->plural('pinches'),
            self::ImperialGallon => U::of('imperial gallon', '0.00454609')->aliases('UK gallon', 'UK gallons', 'gal_imp'),
            self::ImperialQuart => U::of('imperial quart', '0.0011365225')->aliases('UK quart', 'UK quarts'),
            self::ImperialPint => U::of('imperial pint', '0.00056826125')->aliases('UK pint', 'UK pints'),
            self::ImperialFluidOunce => U::of('imperial fluid ounce', '0.0000284130625')->aliases('UK fluid ounce', 'UK fluid ounces'),
            self::ImperialGill => U::of('imperial gill', '0.0001420653125')->aliases('UK gill', 'UK gills'),
            self::ImperialTablespoon => U::of('imperial tablespoon', '0.0000177581640625'),
            self::ImperialTeaspoon => U::of('imperial teaspoon', '0.0000177581640625 / 3'),
            self::ImperialDessertspoon => U::of('imperial dessertspoon', '0.0000177581640625 * 2 / 3')->aliases('imperial dessert spoon'),
            self::ImperialCup => U::of('imperial cup', '0.00454609 / 16')->aliases('UK cup', 'UK cups'),
            self::MetricCup => U::of('metric cup', '0.00025'),
            self::MetricTablespoon => U::of('metric tablespoon', '0.000015')->aliases('international tablespoon', 'osaji', 'ōsaji'),
            self::MetricTeaspoon => U::of('metric teaspoon', '0.000005')->aliases('international teaspoon', 'kosaji'),
            self::MetricPint => U::of('metric pint', '0.0005'),
            self::MetricFluidOunce => U::of('metric fluid ounce', '0.00003'),
            self::Dessertspoon => U::of('dessertspoon', '0.00001')->aliases('dessert spoon', 'dessert spoons', 'dstspn'),
            self::AustralianTablespoon => U::of('Australian tablespoon', '0.00002')->aliases('Aus tablespoon', 'AU tablespoon', 'Aussie tablespoon'),
            self::JapaneseCup => U::of('Japanese cup', '0.0002')->aliases('JP cups'),
            // Japanese volumes are defined from the shō, 2401/1331 litres.
            self::Go => U::of('gō', '2401 / 13310000')->aliases('Japanese go')->plural('gō'),
            self::Sho => U::of('shō', '2401 / 1331000')->aliases('sho', 'Japanese sho')->plural('shō'),
            self::To => U::of('tō', '2401 / 133100')->aliases('Japanese to')->plural('tō'),
            self::Koku => U::of('koku', '2401 / 13310')->plural('koku'),
            self::Barrel => U::of('barrel', '0.158987294928')->aliases('oil barrel', 'oil barrels'),
            self::UsBushel => U::of('bushel', '0.03523907016688')->aliases('US bushel', 'US bushels'),
        };
    }
}
