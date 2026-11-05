<?php

declare(strict_types=1);

namespace Unitary\Currency\Providers;

use Unitary\Currency\Cache\RateCache;
use Unitary\Currency\Currency;
use Unitary\Currency\Http\HttpClient;
use Unitary\Currency\RateTable;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * ExchangeRate-API: ~160 currencies, daily updates. Works without a key via
 * the open access endpoint (attribution required, rate limited); pass an API
 * key to use your plan's endpoint.
 *
 * @see https://www.exchangerate-api.com/docs/free
 */
final class ExchangeRateApi extends RateTableProvider
{
    public function __construct(
        private readonly ?string $apiKey = null,
        ?HttpClient $http = null,
        ?RateCache $cache = null,
        int $ttl = 3600,
    ) {
        parent::__construct($http, $cache, $ttl);
    }

    public function name(): string
    {
        return 'ExchangeRate-API';
    }

    protected function fetch(): RateTable
    {
        $url = $this->apiKey === null
            ? 'https://open.er-api.com/v6/latest/USD'
            : 'https://v6.exchangerate-api.com/v6/'.rawurlencode($this->apiKey).'/latest/USD';

        $data = $this->getJson($url);

        if (($data['result'] ?? null) !== 'success') {
            $reason = $data['error-type'] ?? 'unsuccessful response';

            throw ExchangeRateUnavailableException::fetchFailed($this->name(), is_string($reason) ? $reason : 'unsuccessful response');
        }

        $rates = $data['rates'] ?? $data['conversion_rates'] ?? null;

        return RateTable::fromRaw(
            Currency::USD,
            is_array($rates) ? $rates : [],
            self::dateFromTimestamp($data['time_last_update_unix'] ?? null),
            $this->name(),
        );
    }
}
