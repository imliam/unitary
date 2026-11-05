<?php

declare(strict_types=1);

namespace Unitary\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Unitary\Exceptions\ExchangeRateUnavailableException;
use Unitary\Exceptions\UnitaryException;
use Unitary\PHPStan\ConversionCall;
use Unitary\PHPStan\StaticMethodReturnTypeExtension;
use Unitary\Quantity;
use Unitary\Unitary;

/**
 * Reports literal conversions that would fail at runtime:
 * convert('5 florbs'), Quantity::of(5, 'blorps'), convert('5 kg to m').
 *
 * @implements Rule<CallLike>
 */
final class LiteralConversionRule implements Rule
{
    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $operation = $this->operation($node, $scope);

        if ($operation === null || $node->isFirstClassCallable()) {
            return [];
        }

        [, $error] = ConversionCall::analyse($operation, $node->getArgs(), $scope);

        if (! $error instanceof UnitaryException || $error instanceof ExchangeRateUnavailableException) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Unitary: '.$error->getMessage().'.')
                ->identifier('unitary.invalidLiteral')
                ->build(),
        ];
    }

    private function operation(Node $node, Scope $scope): ?string
    {
        if ($node instanceof FuncCall && $node->name instanceof Name
            && $this->reflectionProvider->resolveFunctionName($node->name, $scope) === 'Unitary\convert') {
            return 'convert';
        }

        if ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Node\Identifier) {
            $class = $scope->resolveName($node->class);
            $method = $node->name->toString();

            return match (true) {
                $class === Unitary::class && in_array($method, ['convert', 'quantity'], true),
                $class === Quantity::class && in_array($method, ['of', 'parse'], true) => StaticMethodReturnTypeExtension::operation($class, $method),
                default => null,
            };
        }

        return null;
    }
}
