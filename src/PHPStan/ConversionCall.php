<?php

declare(strict_types=1);

namespace Unitary\PHPStan;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Throwable;
use Unitary\Quantity;
use Unitary\Time\Timezone;
use Unitary\Time\ZonedTime;

/**
 * The shared logic for convert(), Unitary::convert(), Quantity::of() and
 * friends: work out the result type, or the error, from literal arguments.
 *
 * @internal
 */
final class ConversionCall
{
    /**
     * @param  array<Arg>  $args
     * @return array{?Type, ?Throwable} the precise result type, and the error if the literal can't be converted
     */
    public static function analyse(string $method, array $args, Scope $scope): array
    {
        $args = array_values($args);
        $valueType = isset($args[0]) ? $scope->getType($args[0]->value) : null;
        $unitType = isset($args[1]) ? $scope->getType($args[1]->value) : null;

        if ($valueType === null) {
            return [null, null];
        }

        $value = UnitTypes::literal($valueType);

        if ($method === 'parse' || $method === 'parseQuantity' || ($unitType === null && in_array($method, ['convert', 'quantity'], true))) {
            if (! is_string($value)) {
                return [null, null];
            }

            $converter = UnitTypes::converter();
            [$result, $error] = UnitTypes::attempt(fn () => match ($method) {
                'quantity', 'parseQuantity' => $converter->parseQuantity($value),
                'parse' => $converter->parse($value),
                default => $converter->convert($value),
            });

            return [$result === null ? null : UnitTypes::typeOf($result), $error];
        }

        if ($unitType === null) {
            return [null, null];
        }

        // A value plus a unit: the unit alone decides the type, and the value can be checked when it's a literal.
        $unit = UnitTypes::unitArgument($unitType);

        if ($unit !== null) {
            $error = is_string($value) ? UnitTypes::attempt(fn () => UnitTypes::converter()->quantity($value, $unit))[1] : null;

            return [UnitTypes::quantityType($unit), $error];
        }

        $strings = $unitType->getConstantStrings();

        if (count($strings) !== 1) {
            return [null, null];
        }

        if ($method === 'convert' && Timezone::tryResolve($strings[0]->getValue()) !== null) {
            return [new ObjectType(ZonedTime::class), null];
        }

        return [null, UnitTypes::attempt(fn () => UnitTypes::converter()->units()->resolve($strings[0]->getValue()))[1]];
    }

    public static function isQuantity(Type $type): bool
    {
        return (new ObjectType(Quantity::class))->isSuperTypeOf($type)->yes();
    }
}
