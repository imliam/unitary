<?php

declare(strict_types=1);

namespace Unitary\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Unitary\Dimension;
use Unitary\Unitary;

/**
 * Validates unit text ("kg", "kilometres", "°F"), optionally of one dimension:
 *
 *     new UnitRule(Dimension::Length)
 *     'unit' => 'unit:length'
 */
final readonly class UnitRule implements ValidationRule
{
    public function __construct(private ?Dimension $dimension = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $failure = $this->failure($value);

        if ($failure !== null) {
            $fail('unitary::validation.'.$failure[0])->translate($failure[1]);
        }
    }

    /**
     * @return array{string, array<string, string>}|null
     */
    public function failure(mixed $value): ?array
    {
        $unit = is_string($value) ? Unitary::converter()->units()->tryResolve($value, $this->dimension) : null;

        if ($this->dimension !== null) {
            return $unit?->dimension() === $this->dimension ? null : ['unit_dimension', ['dimension' => $this->dimension->label()]];
        }

        return $unit === null ? ['unit', []] : null;
    }
}
