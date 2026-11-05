<?php

declare(strict_types=1);

namespace Unitary\Currency;

use Brick\Math\BigRational;
use DateTimeImmutable;
use Unitary\Exceptions\ExchangeRateUnavailableException;

/**
 * A snapshot of rates against a single base currency, from which any
 * cross rate can be derived.
 */
final readonly class RateTable
{
    /**
     * @param  array<string, BigRational>  $rates  ISO code => units per one $base
     */
    public function __construct(
        public Currency $base,
        public array $rates,
        public ?DateTimeImmutable $date = null,
        public string $source = '',
    ) {
    }

    /**
     * Builds a table from raw provider data, skipping codes this library doesn't know.
     *
     * @param  array<array-key, mixed>  $rates
     */
    public static function fromRaw(Currency $base, array $rates, ?DateTimeImmutable $date, string $source): self
    {
        $parsed = [$base->value => BigRational::one()];

        foreach ($rates as $code => $rate) {
            $currency = Currency::tryFrom(strtoupper((string) $code));

            if ($currency !== null && (is_string($rate) || is_int($rate) || is_float($rate)) && is_numeric($rate) && $rate > 0) {
                $parsed[$currency->value] = BigRational::of(is_float($rate) ? strtolower(var_export($rate, true)) : (string) $rate);
            }
        }

        return new self($base, $parsed, $date, $source);
    }

    public function has(Currency $currency): bool
    {
        return isset($this->rates[$currency->value]);
    }

    public function rate(Currency $from, Currency $to): ExchangeRate
    {
        $fromRate = $this->rates[$from->value] ?? throw ExchangeRateUnavailableException::for($from, $to, $this->source);
        $toRate = $this->rates[$to->value] ?? throw ExchangeRateUnavailableException::for($from, $to, $this->source);

        return new ExchangeRate($from, $to, $toRate->dividedBy($fromRate)->simplified(), $this->date, $this->source);
    }

    /**
     * @return array{base: string, rates: array<string, string>, date: ?string, source: string}
     */
    public function toArray(): array
    {
        return [
            'base' => $this->base->value,
            'rates' => array_map(fn (BigRational $rate) => (string) $rate, $this->rates),
            'date' => $this->date?->format(DATE_ATOM),
            'source' => $this->source,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $base = Currency::tryFrom(is_string($data['base'] ?? null) ? $data['base'] : '');
        $rates = $data['rates'] ?? null;

        if ($base === null || ! is_array($rates)) {
            return null;
        }

        $date = is_string($data['date'] ?? null) ? new DateTimeImmutable($data['date']) : null;
        $parsed = [];

        foreach ($rates as $code => $rate) {
            if (is_string($rate) && Currency::tryFrom((string) $code) !== null) {
                $parsed[(string) $code] = BigRational::of($rate);
            }
        }

        return new self($base, $parsed, $date, is_string($data['source'] ?? null) ? $data['source'] : '');
    }
}
