<?php

declare(strict_types=1);

namespace Unitary\Currency\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * Adapts any PSR-18 client (requires psr/http-client and psr/http-factory).
 */
final readonly class Psr18HttpClient implements HttpClient
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
    ) {
    }

    public function get(string $url, array $headers = []): string
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw ExchangeRateUnavailableException::fetchFailed($url, $e->getMessage(), $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw ExchangeRateUnavailableException::fetchFailed($url, 'HTTP '.$response->getStatusCode());
        }

        return (string) $response->getBody();
    }
}
