<?php

declare(strict_types=1);

namespace Unitary\Parsing;

use ResourceBundle;
use Unitary\UnitRegistry;

/**
 * Unit names in other languages, from CLDR's data in ICU (ext-intl): "Fuß"
 * and "Zoll" in German, "pieds" in French, "stóp" in Polish, "см" in Russian.
 * Every width, plural form and grammatical case CLDR has is included, mapped
 * to the Unitary symbol for the unit. Without ext-intl there are none, and
 * units are read in English.
 *
 * @internal
 */
final class LocalizedUnitNames
{
    /**
     * Widths from most to least specific. A name from a wider table wins over a
     * clash in a narrower one, where symbols like "″" mean more than one unit.
     */
    private const TABLES = ['units', 'unitsShort', 'unitsNarrow'];

    /**
     * Unit names by locale, mapped to Unitary symbols: as written ("exact"), and lowercased
     * ("folded"). Case matters for some symbols, like German "M" for months and "m" for meters.
     *
     * @var array<string, array{exact: array<string, string>, folded: array<string, string>}>
     */
    private static array $names = [];

    /** @var array<string, string>|null CLDR unit id → Unitary symbol */
    private static ?array $symbols = null;

    /**
     * The Unitary symbol for a unit name in the locale, like "ft" for "Fuß" in German.
     */
    public static function symbolFor(string $name, string $locale): ?string
    {
        $names = self::for($locale);
        $name = UnitRegistry::normalize($name);

        foreach ([$name, rtrim($name, '.')] as $variant) {
            $symbol = $names['exact'][$variant] ?? $names['folded'][mb_strtolower($variant)] ?? null;

            if ($symbol !== null) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * The locale's words for "and" between parts of a measurement, like "und" in German,
     * from CLDR's list patterns ("{0} und {1}").
     *
     * @return list<string>
     */
    public static function conjunctions(string $locale): array
    {
        $conjunctions = [];

        foreach (self::localeChain($locale) as $candidate) {
            $patterns = self::bundle(ResourceBundle::create($candidate, 'ICUDATA', false)?->get('listPattern'));

            foreach (['standard', 'unit'] as $style) {
                $pattern = self::bundle($patterns?->get($style))?->get('2');

                if (is_string($pattern) && preg_match('/^\{0\}\s*(.+?)\s*\{1\}$/u', $pattern, $matches) === 1 && preg_match('/\p{L}/u', $matches[1]) === 1) {
                    $conjunctions[] = mb_strtolower($matches[1]);
                }
            }

            if ($conjunctions !== []) {
                break;
            }
        }

        return array_values(array_unique($conjunctions));
    }

    /**
     * @return array{exact: array<string, string>, folded: array<string, string>}
     */
    private static function for(string $locale): array
    {
        if (isset(self::$names[$locale])) {
            return self::$names[$locale];
        }

        if (! class_exists(ResourceBundle::class)) {
            return self::$names[$locale] = ['exact' => [], 'folded' => []];
        }

        $indexes = ['exact' => [], 'folded' => []];
        $ambiguous = ['exact' => [], 'folded' => []];

        foreach (self::TABLES as $table) {
            $found = ['exact' => [], 'folded' => []];

            foreach (self::localeChain($locale) as $candidate) {
                $units = ResourceBundle::create($candidate, 'ICUDATA-unit', false)?->get($table);

                if (! $units instanceof ResourceBundle) {
                    continue;
                }

                foreach ($units as $category) {
                    if (! $category instanceof ResourceBundle) {
                        continue;
                    }

                    foreach ($category as $id => $patterns) {
                        $symbol = is_string($id) ? (self::symbols()[$id] ?? null) : null;

                        if ($symbol === null || ! $patterns instanceof ResourceBundle) {
                            continue;
                        }

                        foreach (self::namesIn($patterns) as $name) {
                            $found['exact'][$name][$symbol] = true;
                            $found['folded'][mb_strtolower($name)][$symbol] = true;
                        }
                    }
                }
            }

            foreach ($found as $index => $names) {
                foreach ($names as $name => $symbols) {
                    // A name that means two units in the same width, or that a wider width already
                    // gave a different unit, is left for the English rules to read in context.
                    if (count($symbols) > 1 || (isset($indexes[$index][$name]) && $indexes[$index][$name] !== (string) array_key_first($symbols))) {
                        $ambiguous[$index][$name] = true;

                        continue;
                    }

                    $indexes[$index][$name] ??= (string) array_key_first($symbols);
                }
            }
        }

        return self::$names[$locale] = [
            'exact' => array_diff_key($indexes['exact'], $ambiguous['exact']),
            'folded' => array_diff_key($indexes['folded'], $ambiguous['folded']),
        ];
    }

    /**
     * The names in a unit's patterns, like "Fuß" from "{0} Fuß", including every plural form
     * and grammatical case.
     *
     * @return list<string>
     */
    private static function namesIn(ResourceBundle $patterns): array
    {
        $names = [];

        foreach ($patterns as $key => $value) {
            if (in_array($key, ['per', 'gender'], true)) {
                continue;
            }

            if ($value instanceof ResourceBundle) {
                array_push($names, ...self::namesIn($value));

                continue;
            }

            if (! is_string($value) || ($key !== 'dnam' && ! str_contains($value, '{0}'))) {
                continue;
            }

            $name = UnitRegistry::normalize(str_replace('{0}', '', $value));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * ICU returns mixed values, so narrow them to bundles.
     */
    private static function bundle(mixed $value): ?ResourceBundle
    {
        return $value instanceof ResourceBundle ? $value : null;
    }

    /**
     * The locale and the locales it falls back to: "de_CH" → "de_CH", "de", "root". Locales
     * only list what differs from their parent, so German's short "m" for meters comes
     * from the root locale.
     *
     * @return list<string>
     */
    private static function localeChain(string $locale): array
    {
        $chain = [];
        $locale = str_replace('-', '_', $locale);

        while ($locale !== '') {
            $chain[] = $locale;
            $locale = str_contains($locale, '_') ? substr($locale, 0, (int) strrpos($locale, '_')) : '';
        }

        return [...$chain, 'root'];
    }

    /**
     * @return array<string, string>
     */
    private static function symbols(): array
    {
        if (self::$symbols === null) {
            /** @var array{ids: array<string, string>} $file */
            $file = require dirname(__DIR__, 2).'/resources/cldr/unit-ids.php';

            self::$symbols = [];

            foreach ($file['ids'] as $symbol => $id) {
                self::$symbols[$id] ??= (string) $symbol;
            }
        }

        return self::$symbols;
    }
}
