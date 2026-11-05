<?php

declare(strict_types=1);

namespace Unitary\PHPStan;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;
use Unitary\Quantity;
use Unitary\Unitary;

/**
 * Unitary::convert(), Unitary::quantity(), Unitary::unit(), Quantity::of() and Quantity::parse() with literals.
 */
final class StaticMethodReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    /**
     * @param  class-string  $class
     */
    public function __construct(private string $class = Unitary::class)
    {
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return in_array($methodReflection->getName(), $this->class === Quantity::class ? ['of', 'parse'] : ['convert', 'quantity', 'unit'], true);
    }

    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): ?Type
    {
        $method = $methodReflection->getName();

        if ($method === 'unit') {
            $args = $methodCall->getArgs();
            $unit = isset($args[0]) ? UnitTypes::unitArgument($scope->getType($args[0]->value)) : null;

            return $unit === null ? null : UnitTypes::unitType($unit);
        }

        return ConversionCall::analyse(self::operation($this->class, $method), $methodCall->getArgs(), $scope)[0];
    }

    /** Maps a static method onto the ConversionCall operation it performs. */
    public static function operation(string $class, string $method): string
    {
        return match (true) {
            $class === Quantity::class && $method === 'of' => 'quantity_with_unit',
            $class === Quantity::class && $method === 'parse' => 'parseQuantity',
            default => $method,
        };
    }
}
