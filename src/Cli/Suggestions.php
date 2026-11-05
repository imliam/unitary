<?php

declare(strict_types=1);

namespace Unitary\Cli;

use Unitary\UnitRegistry;

/**
 * "Did you mean" hints for unit text the registry doesn't recognize.
 *
 * @internal
 */
final class Suggestions
{
    private const IGNORED_WORDS = ['to', 'in', 'into', 'as', 'and', 'of', 'terms', 'per'];

    /** @var array<string, array{string, string}>|null lower-case spelling => [display spelling, unit key] */
    private ?array $spellings = null;

    public function __construct(private readonly UnitRegistry $units)
    {
    }

    /**
     * Words in the input that aren't units, each with up to three close matches.
     *
     * @return array<string, list<string>>
     */
    public function for(string $input): array
    {
        preg_match_all('/[\p{L}°µμ][\p{L}°µμ\/²³]*/u', $input, $matches);
        $suggestions = [];

        foreach (array_unique($matches[0]) as $word) {
            if (in_array(mb_strtolower($word), self::IGNORED_WORDS, true) || $this->units->candidates($word) !== []) {
                continue;
            }

            $close = $this->closest($word);

            if ($close !== []) {
                $suggestions[$word] = $close;
            }
        }

        return $suggestions;
    }

    /**
     * @return list<string>
     */
    private function closest(string $word): array
    {
        $needle = mb_strtolower($word);
        $limit = max(1, intdiv(mb_strlen($needle), 3));
        $best = [];

        foreach ($this->spellings() as $lower => [$spelling, $unit]) {
            if (abs(strlen($lower) - strlen($needle)) > $limit) {
                continue;
            }

            $distance = levenshtein($needle, (string) $lower);

            // One suggestion per unit: its closest spelling.
            if ($distance <= $limit && (! isset($best[$unit]) || $distance < $best[$unit][1])) {
                $best[$unit] = [$spelling, $distance];
            }
        }

        uasort($best, fn (array $a, array $b) => $a[1] <=> $b[1]);

        return array_slice(array_values(array_map(fn (array $match) => $match[0], $best)), 0, 3);
    }

    /**
     * @return array<string, array{string, string}>
     */
    private function spellings(): array
    {
        if ($this->spellings !== null) {
            return $this->spellings;
        }

        $this->spellings = [];

        foreach ($this->units->all() as $unit) {
            foreach ([$unit->symbol(), $unit->name(), ...$unit->aliases()] as $spelling) {
                // Single characters make for noisy suggestions.
                if (mb_strlen($spelling) > 1) {
                    $this->spellings[mb_strtolower($spelling)] ??= [$spelling, $unit->dimension()->value.':'.$unit->symbol()];
                }
            }
        }

        return $this->spellings;
    }
}
