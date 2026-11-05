<?php

declare(strict_types=1);

namespace Unitary\Substances;

/**
 * The catalogue of known substances. Built-in figures (see resources/substances)
 * come from King Arthur Baking, USGS, US EPA and US DOE AFDC, each cited per property.
 */
final class Substances
{
    /** @var array<string, Substance>|null normalized name/alias => substance */
    private static ?array $index = null;

    /** @var list<Substance> */
    private static array $all = [];

    public static function find(string $name): ?Substance
    {
        $index = self::index();
        $key = self::normalize($name);

        foreach (self::singulars($key) as $candidate) {
            if (isset($index[$candidate])) {
                return $index[$candidate];
            }
        }

        return null;
    }

    /** Adds a substance, taking precedence over built-ins with the same names. */
    public static function register(Substance $substance): void
    {
        self::index();
        self::add($substance);
    }

    /**
     * @return list<Substance>
     */
    public static function all(): array
    {
        self::index();

        return self::$all;
    }

    /** Restores the built-in catalogue. */
    public static function reset(): void
    {
        self::$index = null;
        self::$all = [];
    }

    /**
     * @return array<string, Substance>
     */
    private static function index(): array
    {
        if (self::$index === null) {
            self::$index = [];

            /** @var list<array{name: string, aliases: list<string>, density?: array{string, string}, energy?: array{string, string}, energyBasis?: string, co2?: array{string, string}, sources: array<string, string>, molarMass?: array{string, string}}> $catalogue */
            $catalogue = require dirname(__DIR__, 2).'/resources/substances/catalogue.php';

            foreach ($catalogue as $entry) {
                self::add(Substance::define(
                    $entry['name'],
                    $entry['aliases'],
                    $entry['density'] ?? null,
                    $entry['energy'] ?? null,
                    $entry['energyBasis'] ?? null,
                    $entry['co2'] ?? null,
                    $entry['sources'],
                    $entry['molarMass'] ?? null,
                ));
            }
        }

        return self::$index ?? [];
    }

    private static function add(Substance $substance): void
    {
        self::$all = [...array_values(array_filter(self::$all, fn (Substance $s) => $s->name !== $substance->name)), $substance];

        foreach ([$substance->name, ...$substance->aliases] as $name) {
            self::$index[self::normalize($name)] = $substance;
        }
    }

    private static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['’', '‘'], "'", $name);

        return (string) preg_replace('/\s+/u', ' ', $name);
    }

    /**
     * The name plus singular guesses: "chocolate chips" → "chocolate chip", "pecans" → "pecan".
     *
     * @return list<string>
     */
    private static function singulars(string $name): array
    {
        $candidates = [$name];

        if (str_ends_with($name, 'ies')) {
            $candidates[] = substr($name, 0, -3).'y';
        }

        if (str_ends_with($name, 'es')) {
            $candidates[] = substr($name, 0, -2);
        }

        if (str_ends_with($name, 's')) {
            $candidates[] = substr($name, 0, -1);
        }

        return $candidates;
    }
}
