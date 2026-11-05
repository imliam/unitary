<?php

declare(strict_types=1);

namespace Unitary\Currency\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Stores rate tables in any PSR-16 cache (Laravel's Cache::store(), Symfony's Psr16Cache...).
 * Requires psr/simple-cache.
 */
final readonly class Psr16RateCache implements RateCache
{
    public function __construct(
        private CacheInterface $cache,
        private string $prefix = 'unitary.rates.',
    ) {
    }

    public function get(string $key): ?array
    {
        $value = $this->cache->get($this->prefix.$key);

        return is_array($value) ? $value : null;
    }

    public function set(string $key, array $value, int $ttl): void
    {
        $this->cache->set($this->prefix.$key, $value, $ttl);
    }
}
