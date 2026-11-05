<?php

declare(strict_types=1);

namespace Unitary\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Unitary\Currency\Cache\Psr16RateCache;
use Unitary\Currency\Cache\RateCache;
use Unitary\Currency\ExchangeRateProvider;
use Unitary\Currency\Providers\EuropeanCentralBank;
use Unitary\Currency\Providers\ExchangeRateApi;
use Unitary\Currency\Providers\FixedExchangeRates;
use Unitary\Currency\Providers\Frankfurter;
use Unitary\Currency\Providers\OpenExchangeRates;

/**
 * Builds the exchange rate provider described by config('unitary.exchange_rates').
 */
final readonly class ExchangeRatesFactory
{
    public function __construct(private Container $container)
    {
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public function make(array $config): ?ExchangeRateProvider
    {
        $provider = $config['provider'] ?? null;

        if ($provider === null || $provider === '' || $provider === 'null' || $provider === false) {
            return null;
        }

        if (! is_string($provider)) {
            throw new InvalidArgumentException('unitary.exchange_rates.provider must be a string or null');
        }

        $ttl = is_numeric($config['ttl'] ?? null) ? (int) $config['ttl'] : 3600;
        $cache = $this->cache($config['cache_store'] ?? null);

        return match ($provider) {
            'ecb' => new EuropeanCentralBank(cache: $cache, ttl: $ttl),
            'frankfurter' => new Frankfurter(cache: $cache, ttl: $ttl, baseUrl: self::string($config, 'frankfurter', 'url') ?? 'https://api.frankfurter.dev/v1'),
            'exchangerate-api' => new ExchangeRateApi(self::string($config, 'exchangerate-api', 'key'), cache: $cache, ttl: $ttl),
            'openexchangerates' => new OpenExchangeRates(
                self::string($config, 'openexchangerates', 'app_id')
                    ?? throw new InvalidArgumentException('unitary.exchange_rates.openexchangerates.app_id is required'),
                cache: $cache,
                ttl: $ttl,
            ),
            'fixed' => $this->fixed($config),
            default => $this->fromContainer($provider),
        };
    }

    private function cache(mixed $store): ?RateCache
    {
        if ($store === false || $store === 'false' || ! $this->container->bound(CacheFactory::class) && ! $this->container->bound('cache')) {
            return null;
        }

        $factory = $this->container->make(CacheFactory::class);

        return new Psr16RateCache($factory->store(is_string($store) && $store !== '' ? $store : null));
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    private function fixed(array $config): FixedExchangeRates
    {
        $fixed = is_array($config['fixed'] ?? null) ? $config['fixed'] : [];
        $base = is_string($fixed['base'] ?? null) ? $fixed['base'] : 'USD';
        $rates = [];

        foreach (is_array($fixed['rates'] ?? null) ? $fixed['rates'] : [] as $code => $rate) {
            if (is_string($rate) || is_int($rate) || is_float($rate)) {
                $rates[(string) $code] = $rate;
            }
        }

        return new FixedExchangeRates($base, $rates);
    }

    private function fromContainer(string $provider): ExchangeRateProvider
    {
        if (! class_exists($provider) && ! $this->container->bound($provider)) {
            throw new InvalidArgumentException("Unknown exchange rate provider \"{$provider}\"");
        }

        $instance = $this->container->make($provider);

        return $instance instanceof ExchangeRateProvider
            ? $instance
            : throw new InvalidArgumentException("\"{$provider}\" is not an ".ExchangeRateProvider::class);
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    private static function string(array $config, string $section, string $key): ?string
    {
        $section = $config[$section] ?? null;
        $value = is_array($section) ? ($section[$key] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
