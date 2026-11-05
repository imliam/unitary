<?php

declare(strict_types=1);

namespace Unitary\Currency\Http;

use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * The tiny slice of HTTP the exchange rate providers need. Use NativeHttpClient
 * (no dependencies) or Psr18HttpClient to plug in Guzzle, Symfony HttpClient etc.
 */
interface HttpClient
{
    /**
     * @param  array<string, string>  $headers
     *
     * @throws ExchangeRateUnavailableException on transport errors or non-2xx responses
     */
    public function get(string $url, array $headers = []): string;
}
