<?php

declare(strict_types=1);

namespace Unitary\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Unitary\Converter;

/**
 * @method static \Unitary\Quantity<\Unitary\Unit>|\Unitary\Color\Color|\Unitary\Time\ZonedTime|\Unitary\Geo\Coordinate|\Unitary\Scales\Scale convert(\Brick\Math\BigNumber|int|float|string|\DateTimeInterface $value, \Unitary\Unit|\DateTimeZone|string|null $unit = null)
 * @method static \Unitary\Quantity<\Unitary\Unit> quantity(\Brick\Math\BigNumber|int|float|string $value, \Unitary\Unit|string $unit)
 * @method static \Unitary\Quantity<\Unitary\Unit> convertQuantity(\Unitary\Quantity<\Unitary\Unit> $quantity, \Unitary\Unit|string $to, ?\DateTimeInterface $at = null)
 * @method static \Unitary\Quantity<\Unitary\Unit>|\Unitary\Color\Color|\Unitary\Time\ZonedTime|\Unitary\Geo\Coordinate|\Unitary\Scales\Scale parse(string $input)
 * @method static \Unitary\Quantity<\Unitary\Unit> parseQuantity(string $input, ?\Unitary\Dimension $prefer = null)
 * @method static \Unitary\UnitRegistry units()
 * @method static \Unitary\Currency\ExchangeRateProvider|null exchangeRates()
 * @method static \Unitary\Converter useExchangeRates(?\Unitary\Currency\ExchangeRateProvider $provider)
 *
 * @see Converter
 */
final class Unitary extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Converter::class;
    }
}
