<?php

declare(strict_types=1);

namespace Unitary\PHPStan;

use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\Type;

/**
 * convert('5 kg') is Quantity<Mass::Kilogram>; convert('#fff') is Hex.
 */
final class ConvertFunctionReturnTypeExtension implements DynamicFunctionReturnTypeExtension
{
    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'Unitary\convert';
    }

    public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): ?Type
    {
        return ConversionCall::analyse('convert', $functionCall->getArgs(), $scope)[0];
    }
}
