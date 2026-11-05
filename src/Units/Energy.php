<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: joule. "Calorie" is the thermochemical calorie; "Cal"/"kcal" is the food calorie.
 */
enum Energy: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Joule = 'J';
    case Kilojoule = 'kJ';
    case Megajoule = 'MJ';
    case Calorie = 'cal';
    case Kilocalorie = 'kcal';
    case InternationalCalorie = 'cal_IT';
    case WattHour = 'Wh';
    case KilowattHour = 'kWh';
    case MegawattHour = 'MWh';
    case Electronvolt = 'eV';
    case BritishThermalUnit = 'BTU';
    case ThermochemicalBtu = 'BTU_th';
    case Therm = 'thm';
    case Erg = 'erg';
    case FootPound = 'ft⋅lbf';
    case TonOfTnt = 'tTNT';
    case Quad = 'quad';

    public function dimension(): Dimension
    {
        return Dimension::Energy;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Joule => U::of('joule')->prefixable(),
            self::Kilojoule => U::of('kilojoule', 1000),
            self::Megajoule => U::of('megajoule', '10^6'),
            self::Calorie => U::of('calorie', '4.184')->prefixable()->aliases('small calorie', 'gram calorie'),
            self::Kilocalorie => U::of('kilocalorie', 4184)->aliases('Cal', 'food calorie', 'food calories', 'large calorie', 'kcals'),
            self::InternationalCalorie => U::of('international calorie', '4.1868')->aliases('IT calorie', 'IT calories', 'calorie (IT)', 'steam table calorie'),
            self::WattHour => U::of('watt-hour', 3600)->prefixable()->aliases('watt hour', 'watt hours', 'W h', 'W⋅h'),
            self::KilowattHour => U::of('kilowatt-hour', 3600000)->aliases('kilowatt hour', 'kilowatt hours', 'kW h', 'kW⋅h', 'kwh', 'unit of electricity'),
            self::MegawattHour => U::of('megawatt-hour', '3.6e9')->aliases('megawatt hour', 'megawatt hours'),
            self::Electronvolt => U::of('electronvolt', '1.602176634e-19')->prefixable()->aliases('electron volt', 'electron volts', 'ev'),
            self::BritishThermalUnit => U::of('british thermal unit', '1055.05585262')->aliases('Btu', 'btu', 'BTUs'),
            self::ThermochemicalBtu => U::of('thermochemical british thermal unit', '4.184 * 2267.96185 / 9')->aliases('thermochemical BTU', 'thermochemical BTUs', 'BTU (th)'),
            self::Therm => U::of('therm', '105480400')->aliases('US therm', 'therm US', 'therm (US)', 'US therms'),
            self::Erg => U::of('erg', '10^-7'),
            self::FootPound => U::of('foot-pound', '0.3048 * 4.4482216152605')->aliases('ft-lbf', 'ft lbf', 'ft·lbf', 'ft-lb', 'ft lb', 'foot pound', 'foot pounds'),
            self::TonOfTnt => U::of('ton of TNT', '4.184e9')->aliases('t TNT', 'tons of TNT', 'tonne of TNT', 'tonnes of TNT'),
            self::Quad => U::of('quad', '1055.05585262e15'),
        };
    }
}
