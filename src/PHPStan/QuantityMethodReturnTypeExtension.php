<?php

declare(strict_types=1);

namespace Unitary\PHPStan;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Unitary\CompoundUnit;
use Unitary\Quantity;
use Unitary\Units\Temperature;
use Unitary\Units\TemperatureDifference;

/**
 * Carries units through the types:
 *
 *     Quantity<Length::Kilometer> ÷ Quantity<Time::Hour>  →  Quantity<Speed::KilometerPerHour>
 *     Quantity<Length::Meter>->to('ft')                   →  Quantity<Length::Foot>
 *     Quantity<Speed::KilometerPerHour>->simplify()       →  Quantity<Speed::MeterPerSecond>
 *     Quantity<Temperature::Celsius> − Quantity<Temperature::Celsius>  →  Quantity<TemperatureDifference::Celsius>
 */
final class QuantityMethodReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return Quantity::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), ['to', 'multipliedBy', 'dividedBy', 'power', 'simplify', 'plus', 'minus'], true);
    }

    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $unitType = UnitTypes::quantityUnit($scope->getType($methodCall->var));
        $unit = $unitType === null ? null : UnitTypes::unit($unitType);
        $args = $methodCall->getArgs();
        $argType = isset($args[0]) ? $scope->getType($args[0]->value) : null;
        $registry = UnitTypes::converter()->units();

        if ($unit === null) {
            return null;
        }

        switch ($methodReflection->getName()) {
            case 'to':
                $target = $argType === null ? null : UnitTypes::unitArgument($argType, $unit->dimension());

                if ($unit instanceof TemperatureDifference && $target instanceof Temperature) {
                    $target = $target->difference();
                }

                return $target === null ? null : UnitTypes::quantityType($registry->canonical($target));

            case 'simplify':
                return UnitTypes::quantityType($registry->coherent($unit));

            case 'power':
                $exponent = $argType === null ? null : UnitTypes::literal($argType);

                return is_int($exponent) ? UnitTypes::quantityType($registry->canonical(CompoundUnit::power($unit, $exponent))) : null;
        }

        // multipliedBy / dividedBy another quantity of a known unit.
        $otherUnitType = $argType === null ? null : UnitTypes::quantityUnit($argType);
        $other = $otherUnitType === null ? null : UnitTypes::unit($otherUnitType);

        if ($other === null) {
            return null;
        }

        // Sums keep the left unit, except where temperatures and their differences meet.
        if (in_array($methodReflection->getName(), ['plus', 'minus'], true)) {
            return match (true) {
                $methodReflection->getName() === 'minus' && $unit instanceof Temperature && $other instanceof Temperature => UnitTypes::quantityType($unit->difference()),
                $methodReflection->getName() === 'plus' && $unit instanceof TemperatureDifference && $other instanceof Temperature => UnitTypes::quantityType($other),
                default => null,
            };
        }

        [$result] = UnitTypes::attempt(fn () => $registry->canonical($methodReflection->getName() === 'multipliedBy'
            ? CompoundUnit::product($unit, $other)
            : CompoundUnit::quotient($unit, $other)));

        return $result instanceof \Unitary\Unit ? UnitTypes::quantityType(self::merged($result, $unit, $other)) : null;
    }

    /**
     * Mirrors Quantity's runtime alignment (m × ft is computed as m × m).
     */
    private static function merged(\Unitary\Unit $result, \Unitary\Unit $unit, \Unitary\Unit $other): \Unitary\Unit
    {
        if (! $result instanceof CompoundUnit || $unit->dimension() !== $other->dimension() || ! $unit instanceof \Unitary\MeasurementUnit || ! $unit->definition()->isLinear()) {
            return $result;
        }

        $registry = UnitTypes::converter()->units();
        $factors = array_map(fn (array $factor) => $factor['unit'] === $other ? ['unit' => $unit, 'power' => $factor['power']] : $factor, $result->factors);

        return $registry->canonical(new CompoundUnit($factors));
    }
}
