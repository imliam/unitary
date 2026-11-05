<?php

declare(strict_types=1);

namespace Unitary\Currency\Http;

use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Dependency-free HTTP GET using PHP's stream wrappers.
 */
final readonly class NativeHttpClient implements HttpClient
{
    public function __construct(private float $timeout = 10.0)
    {
    }

    public function get(string $url, array $headers = []): string
    {
        $headers += ['Accept' => 'application/json, text/xml;q=0.9, */*;q=0.8', 'User-Agent' => 'unitary-php'];
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines),
            'timeout' => $this->timeout,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : [];

        if ($body === false) {
            throw ExchangeRateUnavailableException::fetchFailed($url, error_get_last()['message'] ?? 'request failed');
        }

        $status = 0;

        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $status = (int) $matches[1]; // the last status line wins after redirects
            }
        }

        if ($status !== 0 && ($status < 200 || $status >= 300)) {
            throw ExchangeRateUnavailableException::fetchFailed($url, "HTTP {$status}");
        }

        return $body;
    }
}
