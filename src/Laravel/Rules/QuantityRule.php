<?php

declare(strict_types=1);

namespace Unitary\Laravel\Rules;

use Brick\Math\BigNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;
use Unitary\Dimension;
use Unitary\Laravel\Casts\Units;
use Unitary\Laravel\InputLocale;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\Unitary;

/**
 * Validates freeform quantity input:
 *
 *     new QuantityRule(Dimension::Mass)->min('1 g')->max('30 kg')
 *     'weight' => 'quantity:mass,min:1g,max:30kg'
 *
 * Bare numbers are rejected unless a default unit is given with unit().
 */
final class QuantityRule implements ValidationRule
{
    /**
     * @var ?\Unitary\Quantity<\Unitary\Unit>
     */
    private ?Quantity $min = null;

    /**
     * @var ?\Unitary\Quantity<\Unitary\Unit>
     */
    private ?Quantity $max = null;

    private Unit|string|null $unit = null;

    private ?string $locale = null;

    public function __construct(private readonly ?Dimension $dimension = null)
    {
    }

    public static function of(?Dimension $dimension = null): self
    {
        return new self($dimension);
    }

    /**
     * Builds a rule from string rule parameters: a dimension name, plus any of
     * "min:<quantity>", "max:<quantity>" and "unit:<unit for bare numbers>".
     *
     * @param  array<array-key, string>  $parameters
     */
    public static function fromParameters(array $parameters): self
    {
        $dimension = null;
        $options = [];

        foreach ($parameters as $parameter) {
            if (str_contains($parameter, ':')) {
                [$option, $argument] = explode(':', $parameter, 2);
                $options[strtolower(trim($option))] = trim($argument);
            } elseif (trim($parameter) !== '') {
                $dimension = Units::dimension(trim($parameter));
            }
        }

        $rule = new self($dimension);

        if (isset($options['unit'])) {
            $rule->unit($options['unit']);
        }

        if (isset($options['min'])) {
            $rule->min($options['min']);
        }

        if (isset($options['max'])) {
            $rule->max($options['max']);
        }

        return $rule;
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>|string  $min
     */
    public function min(Quantity|string $min): self
    {
        $this->min = $this->quantity($min) ?? throw new \InvalidArgumentException("Invalid minimum quantity \"{$min}\"");

        return $this;
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>|string  $max
     */
    public function max(Quantity|string $max): self
    {
        $this->max = $this->quantity($max) ?? throw new \InvalidArgumentException("Invalid maximum quantity \"{$max}\"");

        return $this;
    }

    /**
     * @param  \Unitary\Quantity<\Unitary\Unit>|string  $max
     * @param  \Unitary\Quantity<\Unitary\Unit>|string  $min
     */
    public function between(Quantity|string $min, Quantity|string $max): self
    {
        return $this->min($min)->max($max);
    }

    /**
     * The locale input is written in, like "de" for "1,5 kg" or "5 Fuß". Defaults to
     * config unitary.locale, else the app's locale. Use "en" to read input the English way.
     */
    public function locale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /** The unit a bare number ("5") is interpreted in. */
    public function unit(Unit|string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $failure = $this->failure($value);

        if ($failure !== null) {
            $fail('unitary::validation.'.$failure[0])->translate($failure[1]);
        }
    }

    /**
     * The translation key (within unitary::validation) and replacements for why
     * the value fails, or null when it passes.
     *
     * @return array{string, array<string, string>}|null
     */
    public function failure(mixed $value): ?array
    {
        $quantity = $this->quantity($value, $this->locale ?? InputLocale::current());

        if ($quantity === null) {
            return ['quantity', []];
        }

        if ($this->dimension !== null && $quantity->dimension() !== $this->dimension) {
            return ['dimension', ['dimension' => $this->dimension->label()]];
        }

        try {
            if ($this->min !== null && $quantity->isLessThan($this->min)) {
                return ['min', ['min' => $this->min->toString()]];
            }

            if ($this->max !== null && $quantity->isGreaterThan($this->max)) {
                return ['max', ['max' => $this->max->toString()]];
            }
        } catch (Throwable) {
            return ['dimension', ['dimension' => ($this->min ?? $this->max)?->dimension()->label() ?? 'quantity']];
        }

        return null;
    }

    /**
     * Limits are written in code, so they're read the English way. Input is read in $locale.
     *
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    private function quantity(mixed $value, string $locale = 'en'): ?Quantity
    {
        if ($value instanceof Quantity) {
            return $value;
        }

        if (! $value instanceof BigNumber && ! is_int($value) && ! is_float($value) && ! (is_string($value) && trim($value) !== '')) {
            return null;
        }

        try {
            return InputLocale::quantity($value, $this->dimension, $this->unit, $locale);
        } catch (Throwable) {
            return null;
        }
    }
}
