<?php

declare(strict_types=1);

namespace Unitary\Currency\Cache;

final class InMemoryRateCache implements RateCache
{
    /** @var array<string, array{value: array<array-key, mixed>, expires: int}> */
    private array $items = [];

    public function get(string $key): ?array
    {
        $item = $this->items[$key] ?? null;

        if ($item === null || $item['expires'] < time()) {
            unset($this->items[$key]);

            return null;
        }

        return $item['value'];
    }

    public function set(string $key, array $value, int $ttl): void
    {
        $this->items[$key] = ['value' => $value, 'expires' => time() + $ttl];
    }
}
