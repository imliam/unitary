<?php

declare(strict_types=1);

namespace Unitary\Exceptions;

use RuntimeException;
use Throwable;
use Unitary\Currency\Currency;

final class ExchangeRateUnavailableException extends RuntimeException implements UnitaryException
{
    public static function noProvider(): self
    {
        return new self(
            'No exchange rate provider is configured. Call Unitary::useExchangeRates(...) with a provider, '
            .'e.g. new \Unitary\Currency\Providers\EuropeanCentralBank().',
        );
    }

    public static function for(Currency $from, Currency $to, string $source, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('No exchange rate from %s to %s is available from %s', $from->value, $to->value, $source),
            previous: $previous,
        );
    }

    public static function fetchFailed(string $source, string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('Failed to fetch exchange rates from %s: %s', $source, $reason), previous: $previous);
    }
}
