<?php

declare(strict_types=1);

namespace Unitary\Tests\Support;

use Unitary\Currency\Http\HttpClient;
use Unitary\Exceptions\ExchangeRateUnavailableException;

final class FakeHttpClient implements HttpClient
{
    /** @var list<string> */
    public array $requests = [];

    /**
     * @param  array<string, string>  $responses  URL prefix => body
     */
    public function __construct(private array $responses)
    {
    }

    public function get(string $url, array $headers = []): string
    {
        $this->requests[] = $url;

        foreach ($this->responses as $prefix => $body) {
            if (str_starts_with($url, $prefix)) {
                return $body;
            }
        }

        throw ExchangeRateUnavailableException::fetchFailed($url, 'HTTP 404');
    }
}
