<?php

declare(strict_types=1);

namespace Unitary\Parsing;

use Brick\Math\BigRational;
use Unitary\Converter;
use Unitary\Currency\Currency;
use Unitary\Dimension;
use Unitary\Exceptions\IncompatibleUnitsException;
use Unitary\Exceptions\ParseException;
use Unitary\MeasurementUnit;
use Unitary\Quantity;
use Unitary\Unit;
use Unitary\UnitRegistry;

/**
 * Parses freeform quantities such as "23 GBP", "£23", "GBP 23", "-40 °C",
 * "1.5e9 nm", "2½ cups", and compound sums like "5 ft 11 in", "13' 11\"",
 * "1 h 30 min" or "2 lb, 3 oz".
 *
 * Compound terms must share a dimension; ambiguous unit text is resolved to
 * whichever reading makes every term compatible ("2h 30m" means minutes).
 * The result is expressed in the smallest unit used (71 in, 90 min).
 *
 * @internal
 */
final readonly class QuantityParser
{
    public function __construct(
        private UnitRegistry $units,
        private Converter $converter,
    ) {
    }

    /**
     * With a locale, numbers may be written the way the locale writes them ("1,5 kg" in
     * German), units may be named in its language ("5 Fuß 10 Zoll"), and parts may be
     * joined with its word for "and" ("5 pieds et 10 pouces"). English still works too.
     *
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function parse(string $input, ?Dimension $prefer = null, ?string $locale = null): Quantity
    {
        return $this->parseText(self::normalize(NumberWords::normalize($locale === null ? $input : self::localize($input, $locale))), $input, $prefer, $locale);
    }

    /**
     * Parses text whose numbers have already been rewritten the English way, so they aren't
     * read a second time as the locale's: German "1,234" is 1.234, not 1234.
     *
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    private function parseText(string $text, string $input, ?Dimension $prefer, ?string $locale): Quantity
    {
        if (preg_match('/^(?<value>.+?)\s*(?:±|\+\/-|\+-)\s*(?<uncertainty>.+)$/u', $text, $matches)) {
            return $this->withUncertainty(trim($matches['value'], ' ('), trim(str_replace(')', '', $matches['uncertainty'])), $input, $prefer, $locale);
        }

        $terms = $this->terms($text, $input, $locale);

        $dimension = $this->commonDimension($terms, $prefer)
            ?? throw ParseException::cannotParse($input, 'quantity', count($terms) > 1
                ? 'the parts do not share a unit dimension'
                : sprintf('unknown unit "%s"', $terms[0]['unit']));

        $quantities = array_map(
            fn (array $term) => new Quantity(
                $term['value'],
                $this->units->resolve($term['unit'], $dimension),
                $this->converter,
                significantFigures: count($terms) === 1 ? $term['significantFigures'] : null,
            ),
            $terms,
        );

        return $this->sum($quantities, $input);
    }

    /**
     * "5.0 ± 0.1 m", "(5.0 ± 0.1) m", "5 m ± 10 cm", "5 m ± 0.1".
     *
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    private function withUncertainty(string $value, string $uncertainty, string $input, ?Dimension $prefer, ?string $locale): Quantity
    {
        try {
            $error = $this->parseText($uncertainty, $uncertainty, $prefer, $locale);
        } catch (ParseException|IncompatibleUnitsException) {
            $error = null;
        }

        if (NumberParser::tryParse($value) !== null && $error !== null) {
            return (new Quantity($value, $error->unit(), $this->converter, $error->value(), NumberParser::significantFigures($value)));
        }

        $quantity = $this->parseText($value, $input, $prefer, $locale);

        if ($error !== null) {
            return $quantity->withUncertainty($error);
        }

        return NumberParser::tryParse($uncertainty) !== null
            ? $quantity->withUncertainty($uncertainty)
            : throw ParseException::cannotParse($input, 'quantity', 'invalid uncertainty');
    }

    /**
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    public function tryParse(string $input, ?Dimension $prefer = null, ?string $locale = null): ?Quantity
    {
        try {
            return $this->parse($input, $prefer, $locale);
        } catch (ParseException|IncompatibleUnitsException) {
            return null;
        }
    }

    /**
     * Rewrites the locale's numbers the English way ("1.234,5" → "1234.5"), its number words
     * and idioms as numerals ("dreieinhalb" → "3 1/2"), and its word for "and" between
     * parts as "and" ("5 Fuß und 10 Zoll" → "5 Fuß and 10 Zoll").
     */
    private static function localize(string $input, string $locale): string
    {
        // Numerals first, so numbers that come from words aren't read as the locale's numerals.
        $text = LocalizedNumberWords::normalize(LocalizedNumbers::normalize($input, $locale), $locale);

        foreach (LocalizedUnitNames::conjunctions($locale) as $conjunction) {
            if ($conjunction !== 'and') {
                $text = (string) preg_replace('/\s+'.preg_quote($conjunction, '/').'\s+(?=[-+]?\d)/iu', ' and ', $text);
            }
        }

        return $text;
    }

    public static function normalize(string $input): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['−', '–', "\u{00A0}", "\u{202F}"], ['-', '-', ' ', ' '], $input)));
    }

    /**
     * Splits the text into signed numeric terms, each with its unit text.
     *
     * @return non-empty-list<array{value: BigRational, unit: string, significantFigures: ?int}>
     */
    private function terms(string $text, string $input, ?string $locale = null): array
    {
        $pattern = '/\G(?<pre>\s*(?:,|\+|&|and\b)?\s*)(?<sign>[-+])?\s*(?<symbol>[^\d\s.,½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅐⅛⅜⅝⅞⅑⅒+-][^\d½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅐⅛⅜⅝⅞⅑⅒]*?)??\s*(?<sign2>-)?'.NumberParser::PATTERN.'/iu';

        $terms = [];
        $offset = 0;
        $length = strlen($text);
        $negative = false;

        while ($offset < $length) {
            if (! preg_match($pattern, $text, $matches, 0, $offset)) {
                break;
            }

            if ($terms === [] && trim($matches['pre']) !== '') {
                break;
            }

            $offset += strlen($matches[0]);
            $value = NumberParser::fromMatches(['sign' => ''] + $matches);
            $symbol = trim($matches['symbol'] ?? '');

            if ($terms === []) {
                $negative = ($matches['sign'] ?? '') === '-' || ($matches['sign2'] ?? '') === '-';
            }

            [$unit, $offset] = $this->unitText($text, $offset);

            // A unit named in the locale's language, like "Fuß", is read as its Unitary symbol.
            if ($locale !== null && $unit !== '') {
                $unit = LocalizedUnitNames::symbolFor($unit, $locale) ?? $unit;
            }

            if ($symbol !== '') {
                // "£23", "US$ 5", "GBP 23": a currency written before the amount, or a rate: "$20/day".
                $isRate = (bool) preg_match('/^(?:\/|per\s)/iu', $unit);

                if (($terms !== [] && ! $isRate) || ($unit !== '' && ! $isRate) || ! $this->isCurrency($symbol)) {
                    throw ParseException::cannotParse($input, 'quantity', sprintf('unexpected "%s"', $symbol));
                }

                $unit = $isRate ? $symbol.($unit[0] === '/' ? '' : ' ').$unit : $symbol;
            }

            if ($unit === '') {
                throw ParseException::cannotParse($input, 'quantity', 'missing unit');
            }

            $written = ($matches['dec'] ?? '') !== '' && ($matches['vulgar'] ?? '') === ''
                ? $matches['dec'].(($matches['exp'] ?? '') !== '' ? 'e'.$matches['exp'] : '')
                : null;

            $terms[] = [
                'value' => $negative ? $value->negated() : $value,
                'unit' => $unit,
                'significantFigures' => $written === null ? null : NumberParser::significantFigures($written),
            ];
        }

        if ($terms === [] || $offset < $length) {
            throw ParseException::cannotParse($input, 'quantity');
        }

        return $terms;
    }

    /**
     * Reads unit text after a number. Digits are only part of the unit when
     * they're exponents or denominators ("m2", "m/s^2", "L/100km").
     *
     * @return array{string, int}
     */
    private function unitText(string $text, int $offset): array
    {
        preg_match('/\G(?:[^\d½⅓⅔¼¾⅕⅖⅗⅘⅙⅚⅐⅛⅜⅝⅞⅑⅒]|(?<=[\/^])-?\d+|(?<=\/)\d+(?=\s?\p{L})|(?<=\p{L})[23](?![\d.,\/]|\s*\d))*/u', $text, $matches, 0, $offset);
        // Units end at the next connector, which may be followed by another term's currency symbol ("$20/day + $300/week").
        $raw = (string) preg_replace('/(?:\s*(?:,|\+|&)|\s+and\b).*$/isu', '', $matches[0] ?? '');
        $unit = $raw;

        // Leave connectors and whitespace for the next term to consume.
        return [trim($unit), $offset + strlen(rtrim($unit))];
    }

    private function isCurrency(string $text): bool
    {
        foreach ($this->units->candidates($text) as $unit) {
            if ($unit instanceof Currency) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds the dimension every term can be read in, favoring the first term's
     * most likely reading (or the preferred dimension).
     *
     * @param  non-empty-list<array{value: BigRational, unit: string, significantFigures: ?int}>  $terms
     */
    private function commonDimension(array $terms, ?Dimension $prefer): ?Dimension
    {
        $dimensions = $this->dimensionsOf($terms[0]['unit']);

        foreach (array_slice($terms, 1) as $term) {
            $candidates = $this->dimensionsOf($term['unit']);
            $dimensions = array_values(array_filter($dimensions, fn (Dimension $d) => in_array($d, $candidates, true)));
        }

        if ($prefer !== null && in_array($prefer, $dimensions, true)) {
            return $prefer;
        }

        // A less likely reading is fine when context asks for it: "100c" in "100c in f" means °C.
        if ($prefer !== null) {
            $readable = array_filter($terms, fn (array $term) => in_array($prefer, $this->dimensionsOf($term['unit'], exhaustive: true), true));

            if (count($readable) === count($terms)) {
                return $prefer;
            }
        }

        return $dimensions[0] ?? null;
    }

    /**
     * @return list<Dimension>
     */
    private function dimensionsOf(string $unit, bool $exhaustive = false): array
    {
        return array_map(fn (Unit $unit) => $unit->dimension(), $this->units->candidates($unit, exhaustive: $exhaustive));
    }

    /**
     * @param  non-empty-list<\Unitary\Quantity<\Unitary\Unit>>  $quantities
     *
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    private function sum(array $quantities, string $input): Quantity
    {
        if (count($quantities) === 1) {
            return $quantities[0];
        }

        $target = $quantities[0]->unit();

        foreach ($quantities as $quantity) {
            $unit = $quantity->unit();

            if ($unit instanceof MeasurementUnit && $target instanceof MeasurementUnit
                && $unit->definition()->factor->isLessThan($target->definition()->factor)) {
                $target = $unit;
            }
        }

        $total = $quantities[0]->to($target);

        foreach (array_slice($quantities, 1) as $quantity) {
            if ($quantity->unit() instanceof MeasurementUnit && ! $quantity->unit()->definition()->isLinear()) {
                throw ParseException::cannotParse($input, 'quantity', 'cannot add temperatures, decibels or other non-linear units');
            }

            $total = $total->plus($quantity);
        }

        return $total;
    }
}
