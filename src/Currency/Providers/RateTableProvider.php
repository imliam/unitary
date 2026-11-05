<?php

declare(strict_types=1);

namespace Unitary\Currency\Providers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use Unitary\Currency\Cache\RateCache;
use Unitary\Currency\Currency;
use Unitary\Currency\ExchangeRate;
use Unitary\Currency\ExchangeRateProvider;
use Unitary\Currency\Http\HttpClient;
use Unitary\Currency\Http\NativeHttpClient;
use Unitary\Currency\RateTable;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Base for providers that publish a table of rates against one base currency.
 * The table is fetched once per instance, and optionally shared via a RateCache.
 */
abstract class RateTableProvider implements ExchangeRateProvider
{
    /** Past rates never change, so historical tables are cached for 30 days. */
    public const HISTORICAL_TTL = 2592000;

    private ?RateTable $table = null;

    /** @var array<string, RateTable> historical tables keyed by requested date */
    private array $historical = [];

    protected readonly HttpClient $http;

    public function __construct(
        ?HttpClient $http = null,
        protected readonly ?RateCache $cache = null,
        protected readonly int $ttl = 3600,
    ) {
        $this->http = $http ?? new NativeHttpClient();
    }

    /** A human-readable name used in error messages and ExchangeRate::$source. */
    abstract public function name(): string;

    /**
     * @throws ExchangeRateUnavailableException
     */
    abstract protected function fetch(): RateTable;

    /** Distinguishes cache entries for differently configured instances. */
    protected function cacheKey(): string
    {
        return strtolower((string) preg_replace('/\W+/', '_', $this->name()));
    }

    public function table(): RateTable
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $cached = $this->cache?->get($this->cacheKey());

        if ($cached !== null && ($table = RateTable::fromArray($cached)) !== null) {
            return $this->table = $table;
        }

        $this->table = $this->fetch();
        $this->cache?->set($this->cacheKey(), $this->table->toArray(), $this->ttl);

        return $this->table;
    }

    /** Discards the in-memory tables so the next lookup re-reads the cache or source. */
    public function refresh(): void
    {
        $this->table = null;
        $this->historical = [];
    }

    /**
     * The table in effect on a date (providers that support history override fetchAt()).
     *
     * @throws ExchangeRateUnavailableException
     */
    public function tableAt(DateTimeInterface $date): RateTable
    {
        $day = DateTimeImmutable::createFromInterface($date)->format('Y-m-d');

        // Allow "tomorrow" so callers ahead of UTC can still ask for their today.
        if ($day > gmdate('Y-m-d', time() + 86400)) {
            throw new ExchangeRateUnavailableException(sprintf('%s has no exchange rates for the future date %s', $this->name(), $day));
        }

        if (isset($this->historical[$day])) {
            return $this->historical[$day];
        }

        $key = $this->cacheKey().'.'.$day;
        $cached = $this->cache?->get($key);

        if ($cached !== null && ($table = RateTable::fromArray($cached)) !== null) {
            return $this->historical[$day] = $table;
        }

        $table = $this->fetchAt(new DateTimeImmutable($day, new DateTimeZone('UTC')));
        $this->cache?->set($key, $table->toArray(), self::HISTORICAL_TTL);

        return $this->historical[$day] = $table;
    }

    /**
     * Fetches the most recent table on or before $date.
     *
     * @throws ExchangeRateUnavailableException
     */
    protected function fetchAt(DateTimeImmutable $date): RateTable
    {
        throw new ExchangeRateUnavailableException(sprintf('%s does not provide historical exchange rates', $this->name()));
    }

    public function rate(Currency $from, Currency $to): ExchangeRate
    {
        return $this->table()->rate($from, $to);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<array-key, mixed>
     */
    protected function getJson(string $url, array $headers = []): array
    {
        try {
            $data = json_decode($this->http->get($url, $headers), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw ExchangeRateUnavailableException::fetchFailed($this->name(), 'invalid JSON response', $e);
        }

        return is_array($data) ? $data : throw ExchangeRateUnavailableException::fetchFailed($this->name(), 'unexpected response');
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected static function arrayAt(array $data, string $key, string $source): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : throw ExchangeRateUnavailableException::fetchFailed($source, "response has no \"{$key}\"");
    }

    protected static function dateFromTimestamp(mixed $timestamp): ?DateTimeImmutable
    {
        return is_int($timestamp) ? (new DateTimeImmutable())->setTimestamp($timestamp) : null;
    }
}
