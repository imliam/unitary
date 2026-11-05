<?php

declare(strict_types=1);

namespace Unitary;

use Brick\Math\BigNumber;
use DateTimeInterface;
use DateTimeZone;
use Unitary\Calc\Calculator;
use Unitary\Color\Color;
use Unitary\Geo\Coordinate;
use Unitary\Scales\Scale;
use Unitary\Currency\ExchangeRateProvider;
use Unitary\Time\ZonedTime;

/**
 * Static access to a shared Converter. For dependency injection, construct a
 * Converter directly instead.
 */
final class Unitary
{
    private static ?Converter $converter = null;

    public static function converter(): Converter
    {
        return self::$converter ??= new Converter(UnitRegistry::default());
    }

    public static function setConverter(?Converter $converter): void
    {
        self::$converter = $converter;
    }

    public static function useExchangeRates(?ExchangeRateProvider $provider): void
    {
        self::converter()->useExchangeRates($provider);
    }

    /**
     * @see Converter::convert()
     *
     * @return ($value is DateTimeInterface ? ZonedTime : ($unit is Unit ? Quantity<Unit> : ($unit is DateTimeZone ? ZonedTime : Quantity<Unit>|Color|ZonedTime|Coordinate|Scale)))
     */
    public static function convert(
        BigNumber|int|float|string|DateTimeInterface $value,
        Unit|DateTimeZone|string|null $unit = null,
    ): Quantity|Color|ZonedTime|Coordinate|Scale {
        return self::converter()->convert($value, $unit);
    }

    /**
     * Like convert(), but returns null instead of throwing when the input can't be converted.
     *
     * @return Quantity<Unit>|Color|ZonedTime|Coordinate|Scale|null
     */
    public static function tryConvert(
        BigNumber|int|float|string|DateTimeInterface $value,
        Unit|DateTimeZone|string|null $unit = null,
    ): Quantity|Color|ZonedTime|Coordinate|Scale|null {
        try {
            return self::converter()->convert($value, $unit);
        } catch (Exceptions\UnitaryException) {
            return null;
        }
    }

    /**
     * Whether the input means anything: "5 ft 11 in", "£23 in USD", "25% of 300",
     * "#ff0000" and "9am PST" do; "asdf", "5 florbs" and "5 kg to m" don't.
     * Never touches the network.
     */
    public static function understands(string $input, bool $allowLabels = false): bool
    {
        return self::converter()->understands($input, $allowLabels);
    }

    /** A quantity from a number and unit, or from freeform text such as "5 ft 11 in". */
    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public static function quantity(BigNumber|int|float|string $value, Unit|string|null $unit = null): Quantity
    {
        if ($unit === null) {
            return self::converter()->parseQuantity((string) $value);
        }

        return self::converter()->quantity($value, $unit);
    }

    public static function color(string $color): Color
    {
        return Color::parse($color);
    }

    public static function time(DateTimeInterface|string $time, DateTimeZone|string|null $zone = null): ZonedTime
    {
        return ZonedTime::of($time, $zone);
    }

    /**
     * Evaluates calculator-style input: "(5 ft + 30 cm) × 2 in m", "25% of $300",
     * "3 pizzas at $12 each + 20% tip". Use calculator() to keep variables between lines.
     *
     * @return Quantity<Unit>|Color|ZonedTime|Coordinate|Scale|null
     */
    public static function evaluate(string $expression): Quantity|Color|ZonedTime|Coordinate|Scale|null
    {
        return self::calculator()->evaluate($expression);
    }

    /** A fresh notepad-style calculator sheet. */
    public static function calculator(): Calculator
    {
        return new Calculator(self::converter());
    }

    public static function unit(string $unit, ?Dimension $prefer = null): Unit
    {
        return self::converter()->units()->resolve($unit, $prefer);
    }
}
