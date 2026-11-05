<?php

declare(strict_types=1);

namespace Unitary\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Unitary\Currency\Currency;

/**
 * Validates an ISO 4217 currency code, case-insensitively: 'currency' or new CurrencyRule().
 */
final readonly class CurrencyRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->failure($value) !== null) {
            $fail('unitary::validation.currency')->translate();
        }
    }

    /**
     * @return array{string, array<string, string>}|null
     */
    public function failure(mixed $value): ?array
    {
        return is_string($value) && Currency::tryFrom(strtoupper(trim($value))) !== null ? null : ['currency', []];
    }
}
