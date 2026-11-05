<?php

declare(strict_types=1);

namespace Unitary\Currency\Cache;

interface RateCache
{
    /**
     * @return array<array-key, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param  array<array-key, mixed>  $value
     */
    public function set(string $key, array $value, int $ttl): void;
}
