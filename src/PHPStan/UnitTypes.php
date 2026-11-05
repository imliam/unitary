<?php

declare(strict_types=1);

namespace Unitary\PHPStan;

use PHPStan\Type\Enum\EnumCaseObjectType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Throwable;
use Unitary\Converter;
use Unitary\Dimension;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\UnitRegistry;

/**
 * Bridges PHPStan types and Unitary's runtime: the extension runs the real
 * parser over string literals, with every exchange rate fixed at 1 so that
 * currency conversions type-check without network access.
 *
 * @internal
 */
final class UnitTypes
{
    public static function converter(): Converter
    {
        /** @var Converter|null $converter */
        static $converter = null;

        return $converter ??= (new Converter(UnitRegistry::default()))->offline();
    }

    /**
     * Every unit an expression's type could be: one for `Length::Meter`, all
     * cases for `Length`, none for `Unit` or non-enum units.
     *
     * @return list<Unit>
     */
    public static function units(Type $type): array
    {
        $units = [];

        foreach ($type->getEnumCases() as $case) {
            $value = constant($case->getClassName().'::'.$case->getEnumCaseName());

            if ($value instanceof Unit) {
                $units[] = $value;
            }
        }

        return $units;
    }

    /** The single unit a type stands for, if it's exactly one enum case. */
    public static function unit(Type $type): ?Unit
    {
        $units = self::units($type);

        return count($units) === 1 ? $units[0] : null;
    }

    /** The dimension shared by every unit the type could be. */
    public static function dimension(Type $type): ?Dimension
    {
        $dimensions = array_unique(array_map(fn (Unit $unit) => $unit->dimension()->value, self::units($type)));

        return count($dimensions) === 1 ? Dimension::from($dimensions[0]) : null;
    }

    /** TUnit of a Quantity<TUnit> type. */
    public static function quantityUnit(Type $type): ?Type
    {
        if (! (new ObjectType(Quantity::class))->isSuperTypeOf($type)->yes()) {
            return null;
        }

        return $type->getTemplateType(Quantity::class, 'TUnit');
    }

    /** A unit argument given as an enum case or a literal string. */
    public static function unitArgument(Type $type, ?Dimension $prefer = null): ?Unit
    {
        $unit = self::unit($type);

        if ($unit !== null) {
            return $unit;
        }

        $strings = $type->getConstantStrings();

        return count($strings) === 1 ? self::converter()->units()->tryResolve($strings[0]->getValue(), $prefer) : null;
    }

    /** The single literal value of a type (string, int or float), if it has one. */
    public static function literal(Type $type): string|int|float|null
    {
        $values = $type->getConstantScalarValues();

        if (count($values) !== 1) {
            return null;
        }

        return is_string($values[0]) || is_int($values[0]) || is_float($values[0]) ? $values[0] : null;
    }

    public static function unitType(Unit $unit): Type
    {
        return $unit instanceof \UnitEnum ? new EnumCaseObjectType($unit::class, $unit->name) : new ObjectType($unit::class);
    }

    public static function quantityType(Unit $unit): Type
    {
        return new GenericObjectType(Quantity::class, [self::unitType($unit)]);
    }

    /** The precise type of a value produced at analysis time. */
    public static function typeOf(object $value): Type
    {
        return $value instanceof Quantity ? self::quantityType($value->unit()) : new ObjectType($value::class);
    }

    /**
     * Runs a conversion at analysis time.
     *
     * @return array{object|null, ?Throwable}
     */
    public static function attempt(callable $conversion): array
    {
        try {
            $result = $conversion();

            return [is_object($result) ? $result : null, null];
        } catch (Throwable $e) {
            return [null, $e];
        }
    }
}
