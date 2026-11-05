<?php

declare(strict_types=1);

namespace Unitary\Laravel;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Validator;
use Unitary\Converter;
use Unitary\Laravel\Casts\Units;
use Unitary\Laravel\Rules\CurrencyRule;
use Unitary\Laravel\Rules\QuantityRule;
use Unitary\Laravel\Rules\UnitRule;
use Unitary\Unitary;
use Unitary\UnitRegistry;
use WeakMap;

final class UnitaryServiceProvider extends ServiceProvider
{
    /** @var WeakMap<Validator, array<string, array{string, array<string, string>}>>|null */
    private static ?WeakMap $failures = null;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/unitary.php', 'unitary');

        $this->app->singleton(ExchangeRatesFactory::class);

        $this->app->singleton(Converter::class, function (Application $app): Converter {
            $config = $app->make(ConfigRepository::class)->get('unitary.exchange_rates', []);

            return new Converter(
                UnitRegistry::default(),
                $app->make(ExchangeRatesFactory::class)->make(is_array($config) ? $config : []),
            );
        });

        $this->app->alias(Converter::class, 'unitary');
    }

    public function boot(): void
    {
        // Point the static helpers (convert(), Quantity::of()...) at the container's converter.
        Unitary::setConverter($this->app->make(Converter::class));

        $this->publishes([__DIR__.'/../../config/unitary.php' => $this->app->configPath('unitary.php')], 'unitary-config');
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'unitary');
        $this->publishes([__DIR__.'/resources/lang' => $this->app->langPath('vendor/unitary')], 'unitary-lang');

        Blade::directive('quantity', fn (string $expression) => "<?php echo e(\\Unitary\\Laravel\\QuantityFormatter::format({$expression})); ?>");

        Macros::register();

        $this->callAfterResolving(ValidationFactory::class, function (ValidationFactory $validator): void {
            $this->registerRule($validator, 'quantity', fn (array $parameters) => QuantityRule::fromParameters($parameters));
            $this->registerRule($validator, 'unit', fn (array $parameters) => new UnitRule(Units::dimension($parameters[0] ?? null)));
            $this->registerRule($validator, 'currency', fn () => new CurrencyRule());
        });
    }

    /**
     * Registers a string rule whose message depends on why it failed (e.g. min
     * vs. max), by remembering the failure for the replacer.
     *
     * @param  Closure(array<array-key, string>): (QuantityRule|UnitRule|CurrencyRule)  $make
     */
    private function registerRule(ValidationFactory $factory, string $name, Closure $make): void
    {
        self::$failures ??= new WeakMap();
        $failures = self::$failures;

        $factory->extend($name, function (string $attribute, mixed $value, array $parameters, Validator $validator) use ($make, $failures): bool {
            /** @var array<array-key, string> $parameters */
            $failure = $make($parameters)->failure($value);

            if ($failure === null) {
                return true;
            }

            $failures[$validator] = [...($failures[$validator] ?? []), $attribute => $failure];

            return false;
        }, 'unitary::validation.'.$name);

        $factory->replacer($name, function (string $message, string $attribute, string $rule, array $parameters, Validator $validator) use ($failures): string {
            $failure = $failures[$validator][$attribute] ?? null;

            if ($failure === null) {
                return $message;
            }

            $translated = trans('unitary::validation.'.$failure[0], [...$failure[1], 'attribute' => $validator->getDisplayableAttribute($attribute)]);

            return is_string($translated) ? $translated : $message;
        });
    }
}
