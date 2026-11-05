<?php

declare(strict_types=1);

namespace Unitary\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Unitary\Converter;
use Unitary\PHPStan\UnitTypes;
use Unitary\Unit;
use Unitary\Units\Temperature;
use Unitary\Units\TemperatureDifference;

/**
 * Catches unit mistakes at analysis time:
 *
 *     $length->plus($mass)         Cannot add mass to length
 *     $length->to('kg')            Cannot convert length to kg (mass)
 *     $length->isGreaterThan($t)   Cannot compare length with time
 *
 * @implements Rule<MethodCall>
 */
final class QuantityDimensionRule implements Rule
{
    private const VERBS = [
        'plus' => 'add %2$s to %1$s',
        'minus' => 'subtract %2$s from %1$s',
        'compareTo' => 'compare %1$s with %2$s',
        'isEqualTo' => 'compare %1$s with %2$s',
        'isGreaterThan' => 'compare %1$s with %2$s',
        'isGreaterThanOrEqualTo' => 'compare %1$s with %2$s',
        'isLessThan' => 'compare %1$s with %2$s',
        'isLessThanOrEqualTo' => 'compare %1$s with %2$s',
        'withUncertainty' => 'use %2$s as the uncertainty of %1$s',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier || $node->isFirstClassCallable()) {
            return [];
        }

        $method = $node->name->toString();
        $unitType = UnitTypes::quantityUnit($scope->getType($node->var));
        $units = $unitType === null ? [] : UnitTypes::units($unitType);
        $arg = $node->getArgs()[0] ?? null;

        if ($units === [] || $arg === null || ($method !== 'to' && ! isset(self::VERBS[$method]))) {
            return [];
        }

        $argType = $scope->getType($arg->value);

        if ($method === 'to') {
            return $this->checkTarget($units, $argType);
        }

        $otherUnitType = UnitTypes::quantityUnit($argType);
        $others = $otherUnitType === null ? [] : UnitTypes::units($otherUnitType);

        if ($others === [] || $this->anyCompatible($units, $others, $method)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf('Unitary: Cannot '.self::VERBS[$method].'.', self::describe($units), self::describe($others)))
                ->identifier('unitary.incompatibleDimensions')
                ->build(),
        ];
    }

    /**
     * @param  non-empty-list<Unit>  $units
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkTarget(array $units, \PHPStan\Type\Type $argType): array
    {
        $strings = $argType->getConstantStrings();

        if (count($strings) === 1) {
            $text = $strings[0]->getValue();
            $target = UnitTypes::converter()->units()->tryResolve($text, $units[0]->dimension());

            if ($target === null) {
                return [RuleErrorBuilder::message(sprintf('Unitary: Unknown unit "%s".', $text))->identifier('unitary.unknownUnit')->build()];
            }

            $targets = [$target];
        } else {
            $targets = UnitTypes::units($argType);
        }

        if ($targets === [] || $this->anyCompatible($units, $targets, 'to')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf('Unitary: Cannot convert %s to %s.', self::describe($units), self::describe($targets)))
                ->identifier('unitary.incompatibleDimensions')
                ->build(),
        ];
    }

    /**
     * @param  list<Unit>  $a
     * @param  list<Unit>  $b
     */
    private function anyCompatible(array $a, array $b, string $method): bool
    {
        foreach ($a as $x) {
            foreach ($b as $y) {
                if (Converter::compatible($x, $y) || self::temperatureChange($method, $x, $y)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Temperatures and their differences mix in sums: 20 °C + 5 Δ°F, 5 Δ°C + 20 °C,
     * 30 °C − 5 Δ°C, and a difference converts to a scale's degrees (Δ°C to °F).
     */
    private static function temperatureChange(string $method, Unit $x, Unit $y): bool
    {
        return match ($method) {
            'plus' => ($x instanceof Temperature && $y instanceof TemperatureDifference) || ($x instanceof TemperatureDifference && $y instanceof Temperature),
            'minus' => $x instanceof Temperature && $y instanceof TemperatureDifference,
            'to' => $x instanceof TemperatureDifference && $y instanceof Temperature,
            default => false,
        };
    }

    /**
     * @param  non-empty-list<Unit>  $units
     */
    private static function describe(array $units): string
    {
        $dimension = $units[0]->dimension()->label();

        return count($units) === 1 ? "{$dimension} ({$units[0]->symbol()})" : $dimension;
    }
}
