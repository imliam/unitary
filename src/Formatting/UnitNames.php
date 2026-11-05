<?php

declare(strict_types=1);

namespace Unitary\Formatting;

use Brick\Math\BigRational;
use MessageFormatter;
use Unitary\CompoundUnit;
use Unitary\Currency\Currency;
use Unitary\MeasurementUnit;
use Unitary\PrefixedUnit;
use Unitary\Unit;
use Unitary\UnitDefinition;
use Unitary\UnitDisplay;

/**
 * Writes a unit next to an already formatted number, in a language and width:
 * "2 inches", "2 pouces", "2″".
 *
 * ICU (ext-intl) knows the unit names and plural rules for every CLDR locale,
 * but it formats numbers as floats. So ICU formats the value with the same
 * number of decimals, which picks the right plural form ("1,5 pouce" but
 * "1,5 cala" in Polish), and the exact number is put in place of ICU's.
 * Without ext-intl, or for units CLDR doesn't have, the names are Unitary's
 * own English ones.
 *
 * @internal
 */
final class UnitNames
{
    /** @var array<string, string>|null Unitary symbol → CLDR unit id */
    private static ?array $ids = null;

    /**
     * @param  string  $number  the formatted number, possibly with "± uncertainty"
     * @param  BigRational  $value  the value, for choosing between singular and plural
     * @param  int  $fractionDigits  how many decimals $number shows
     */
    public static function join(string $number, BigRational $value, int $fractionDigits, Unit $unit, UnitDisplay $display, ?string $locale): string
    {
        return self::localized($number, $value, $fractionDigits, $unit, $display, $locale ?? 'en')
            ?? self::fallback($number, $value, $unit, $display);
    }

    /**
     * The CLDR id for a unit: "kilometer-per-hour", "square-foot", "gigawatt".
     */
    public static function cldrId(Unit $unit): ?string
    {
        if ($unit instanceof PrefixedUnit) {
            $base = self::cldrId($unit->unit);
            $prefix = $unit->prefix->name() === 'deca' ? 'deka' : $unit->prefix->name();

            return $base === null ? null : $prefix.$base;
        }

        if ($unit instanceof CompoundUnit) {
            return self::compoundId($unit);
        }

        if (self::$ids === null) {
            /** @var array{ids: array<string, string>} $file */
            $file = require dirname(__DIR__, 2).'/resources/cldr/unit-ids.php';
            self::$ids = $file['ids'];
        }

        return self::$ids[$unit->symbol()] ?? null;
    }

    private static function compoundId(CompoundUnit $unit): ?string
    {
        $sides = [[], []];

        foreach ($unit->factors as ['unit' => $factor, 'power' => $power]) {
            $id = $factor instanceof Currency ? null : self::cldrId($factor);

            if ($id === null) {
                return null;
            }

            $sides[$power < 0 ? 1 : 0][] = match (abs($power)) {
                1 => $id,
                2 => 'square-'.$id,
                3 => 'cubic-'.$id,
                default => 'pow'.abs($power).'-'.$id,
            };
        }

        // CLDR ids need something on top, so 1/s has none.
        if ($sides[0] === []) {
            return null;
        }

        return implode('-', $sides[0]).($sides[1] === [] ? '' : '-per-'.implode('-', $sides[1]));
    }

    private static function localized(string $number, BigRational $value, int $fractionDigits, Unit $unit, UnitDisplay $display, string $locale): ?string
    {
        if (! class_exists(MessageFormatter::class)) {
            return null;
        }

        $width = match ($display) {
            UnitDisplay::Narrow => 'unit-width-narrow',
            UnitDisplay::Short => 'unit-width-short',
            UnitDisplay::Long => 'unit-width-full-name',
        };
        $id = $unit instanceof Currency ? null : self::cldrId($unit);
        $stem = match (true) {
            $unit instanceof Currency => 'currency/'.$unit->value,
            $id !== null => 'unit/'.$id,
            default => null,
        };

        if ($stem === null) {
            return null;
        }

        $precision = $fractionDigits > 0 ? '.'.str_repeat('0', min($fractionDigits, 20)) : 'precision-integer';
        $float = $value->toFloat();
        $withUnit = @MessageFormatter::formatMessage($locale, "{0, number, ::{$stem} {$width} {$precision}}", [$float]);
        $bare = @MessageFormatter::formatMessage($locale, "{0, number, ::{$precision}}", [$float]);

        if (! is_string($withUnit) || ! is_string($bare) || $bare === '' || ! str_contains($withUnit, $bare)) {
            return null;
        }

        $position = (int) strpos($withUnit, $bare);

        return substr($withUnit, 0, $position).$number.substr($withUnit, $position + strlen($bare));
    }

    private static function fallback(string $number, BigRational $value, Unit $unit, UnitDisplay $display): string
    {
        if ($unit instanceof Currency) {
            return match ($display) {
                UnitDisplay::Long => "{$number} {$unit->value}",
                default => self::currencySymbol($number, $unit),
            };
        }

        $symbol = $unit->symbol();

        if ($symbol === '') {
            return $number;
        }

        return match ($display) {
            UnitDisplay::Narrow => $number.$symbol,
            UnitDisplay::Short => preg_match('/^[°′″\'"%‰]$/u', $symbol) === 1 ? $number.$symbol : "{$number} {$symbol}",
            UnitDisplay::Long => $number.' '.($value->abs()->isEqualTo(1) ? $unit->name() : self::plural($unit)),
        };
    }

    private static function plural(Unit $unit): string
    {
        return $unit instanceof MeasurementUnit ? $unit->definition()->pluralForms()[0] : UnitDefinition::pluralise($unit->name());
    }

    private static function currencySymbol(string $number, Currency $currency): string
    {
        $symbol = $currency->displaySymbol();

        if (preg_match('/^\P{L}|\$$/u', $symbol) === 1) {
            return str_starts_with($number, '-') ? '-'.$symbol.substr($number, 1) : $symbol.$number;
        }

        return "{$number} {$symbol}";
    }
}
