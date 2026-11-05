<?php

declare(strict_types=1);

namespace Unitary\Cli;

use Unitary\Currency\Cache\RateCache;

/**
 * Keeps fetched rate tables on disk between CLI invocations. Failures to read
 * or write are ignored; the worst case is a fresh fetch.
 */
final readonly class FileRateCache implements RateCache
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? sys_get_temp_dir().DIRECTORY_SEPARATOR.'unitary', DIRECTORY_SEPARATOR);
    }

    public function get(string $key): ?array
    {
        $path = $this->path($key);
        $contents = is_file($path) ? @file_get_contents($path) : false;

        if ($contents === false) {
            return null;
        }

        $item = json_decode($contents, true);

        if (! is_array($item) || ! is_int($item['expires'] ?? null) || ! is_array($item['value'] ?? null) || $item['expires'] < time()) {
            return null;
        }

        return $item['value'];
    }

    public function set(string $key, array $value, int $ttl): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0o777, true) && ! is_dir($this->directory)) {
            return;
        }

        $json = json_encode(['expires' => time() + $ttl, 'value' => $value]);
        $temporary = $this->path($key).'.'.getmypid().'.tmp';

        if ($json !== false && @file_put_contents($temporary, $json) !== false) {
            @rename($temporary, $this->path($key));
        }
    }

    private function path(string $key): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.md5($key).'.json';
    }
}
