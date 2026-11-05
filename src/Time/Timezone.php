<?php

declare(strict_types=1);

namespace Unitary\Time;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Unitary\Exceptions\ParseException;

/**
 * Resolves human-friendly timezone input to a DateTimeZone.
 *
 * Accepted forms, tried in order:
 *  - UTC aliases: "UTC", "GMT", "UT", "Z"
 *  - UTC offsets: "+05:30", "-0800", "+01", "UTC+1", "GMT-5", "UTC+05:30"
 *  - Abbreviations: "EST", "PDT", "BST"... (see ABBREVIATIONS). These resolve to a
 *    representative IANA zone rather than a fixed offset, so DST is applied by date.
 *  - Canonical IANA identifiers, case-insensitively: "europe/london"
 *  - Curated city and country names: "San Francisco", "Mumbai", "UK", "Japan"
 *  - Backward-compatible IANA identifiers: "US/Eastern", "GB"
 *  - The last segment of any IANA identifier: "Tokyo", "new york", "Sao Paulo", "São Paulo"
 */
final class Timezone
{
    /**
     * Abbreviation => representative IANA zone. Abbreviations are ambiguous by nature,
     * so this picks the most common meaning in English usage (e.g. IST is India, not
     * Ireland or Israel; CST is US Central, not China).
     */
    public const ABBREVIATIONS = [
        'ET' => 'America/New_York', 'EST' => 'America/New_York', 'EDT' => 'America/New_York',
        'CT' => 'America/Chicago', 'CST' => 'America/Chicago', 'CDT' => 'America/Chicago',
        'MT' => 'America/Denver', 'MST' => 'America/Denver', 'MDT' => 'America/Denver',
        'PT' => 'America/Los_Angeles', 'PST' => 'America/Los_Angeles', 'PDT' => 'America/Los_Angeles',
        'AKST' => 'America/Anchorage', 'AKDT' => 'America/Anchorage',
        'HST' => 'Pacific/Honolulu', 'HDT' => 'Pacific/Honolulu',
        'AST' => 'America/Halifax', 'ADT' => 'America/Halifax',
        'NST' => 'America/St_Johns', 'NDT' => 'America/St_Johns',
        'BRT' => 'America/Sao_Paulo',
        'ART' => 'America/Argentina/Buenos_Aires',
        'CLT' => 'America/Santiago', 'CLST' => 'America/Santiago',
        'BST' => 'Europe/London',
        'WET' => 'Europe/Lisbon', 'WEST' => 'Europe/Lisbon',
        'CET' => 'Europe/Paris', 'CEST' => 'Europe/Paris',
        'EET' => 'Europe/Athens', 'EEST' => 'Europe/Athens',
        'MSK' => 'Europe/Moscow',
        'SAST' => 'Africa/Johannesburg',
        'WAT' => 'Africa/Lagos',
        'CAT' => 'Africa/Maputo',
        'EAT' => 'Africa/Nairobi',
        'GST' => 'Asia/Dubai',
        'PKT' => 'Asia/Karachi',
        'IST' => 'Asia/Kolkata',
        'NPT' => 'Asia/Kathmandu',
        'ICT' => 'Asia/Bangkok',
        'WIB' => 'Asia/Jakarta',
        'SGT' => 'Asia/Singapore',
        'HKT' => 'Asia/Hong_Kong',
        'PHT' => 'Asia/Manila',
        'JST' => 'Asia/Tokyo',
        'KST' => 'Asia/Seoul',
        'AWST' => 'Australia/Perth',
        'ACST' => 'Australia/Adelaide', 'ACDT' => 'Australia/Adelaide',
        'AEST' => 'Australia/Sydney', 'AEDT' => 'Australia/Sydney',
        'NZST' => 'Pacific/Auckland', 'NZDT' => 'Pacific/Auckland',
    ];

    /**
     * Abbreviations that are also everyday English words; these only match when
     * written in upper case so "cat" or "art" is never mistaken for a timezone.
     */
    private const WORDLIKE_ABBREVIATIONS = ['ART', 'CAT', 'EAT', 'WAT', 'ET', 'CT', 'MT', 'PT'];

    /**
     * Popular places that aren't the last segment of an IANA identifier, plus countries
     * that observe a single dominant timezone. Keys are normalized (see normalize()).
     */
    public const PLACES = [
        // United States
        'san francisco' => 'America/Los_Angeles', 'seattle' => 'America/Los_Angeles',
        'portland' => 'America/Los_Angeles', 'las vegas' => 'America/Los_Angeles',
        'san diego' => 'America/Los_Angeles', 'san jose' => 'America/Los_Angeles',
        'boston' => 'America/New_York', 'washington' => 'America/New_York',
        'washington dc' => 'America/New_York', 'dc' => 'America/New_York',
        'philadelphia' => 'America/New_York', 'atlanta' => 'America/New_York',
        'miami' => 'America/New_York', 'nyc' => 'America/New_York',
        'dallas' => 'America/Chicago', 'houston' => 'America/Chicago',
        'austin' => 'America/Chicago', 'minneapolis' => 'America/Chicago',
        'new orleans' => 'America/Chicago', 'salt lake city' => 'America/Denver',
        'la' => 'America/Los_Angeles', 'hawaii' => 'Pacific/Honolulu', 'alaska' => 'America/Anchorage',
        // Canada
        'montreal' => 'America/Toronto', 'ottawa' => 'America/Toronto', 'calgary' => 'America/Edmonton',
        // Europe
        'uk' => 'Europe/London', 'united kingdom' => 'Europe/London', 'great britain' => 'Europe/London',
        'britain' => 'Europe/London', 'england' => 'Europe/London', 'scotland' => 'Europe/London',
        'wales' => 'Europe/London', 'edinburgh' => 'Europe/London', 'manchester' => 'Europe/London',
        'ireland' => 'Europe/Dublin', 'france' => 'Europe/Paris', 'germany' => 'Europe/Berlin',
        'munich' => 'Europe/Berlin', 'frankfurt' => 'Europe/Berlin', 'hamburg' => 'Europe/Berlin',
        'spain' => 'Europe/Madrid', 'barcelona' => 'Europe/Madrid', 'italy' => 'Europe/Rome',
        'milan' => 'Europe/Rome', 'netherlands' => 'Europe/Amsterdam', 'holland' => 'Europe/Amsterdam',
        'belgium' => 'Europe/Brussels', 'switzerland' => 'Europe/Zurich', 'geneva' => 'Europe/Zurich',
        'austria' => 'Europe/Vienna', 'sweden' => 'Europe/Stockholm', 'norway' => 'Europe/Oslo',
        'denmark' => 'Europe/Copenhagen', 'finland' => 'Europe/Helsinki', 'poland' => 'Europe/Warsaw',
        'greece' => 'Europe/Athens', 'turkey' => 'Europe/Istanbul', 'ukraine' => 'Europe/Kyiv',
        'kiev' => 'Europe/Kyiv', 'st petersburg' => 'Europe/Moscow', 'saint petersburg' => 'Europe/Moscow',
        'portugal' => 'Europe/Lisbon', 'czechia' => 'Europe/Prague', 'czech republic' => 'Europe/Prague',
        'hungary' => 'Europe/Budapest', 'romania' => 'Europe/Bucharest', 'iceland' => 'Atlantic/Reykjavik',
        // Asia & Middle East
        'beijing' => 'Asia/Shanghai', 'china' => 'Asia/Shanghai', 'shenzhen' => 'Asia/Shanghai',
        'guangzhou' => 'Asia/Shanghai', 'mumbai' => 'Asia/Kolkata', 'bombay' => 'Asia/Kolkata',
        'delhi' => 'Asia/Kolkata', 'new delhi' => 'Asia/Kolkata', 'bangalore' => 'Asia/Kolkata',
        'bengaluru' => 'Asia/Kolkata', 'chennai' => 'Asia/Kolkata', 'hyderabad' => 'Asia/Kolkata',
        'india' => 'Asia/Kolkata', 'japan' => 'Asia/Tokyo', 'osaka' => 'Asia/Tokyo',
        'kyoto' => 'Asia/Tokyo', 'korea' => 'Asia/Seoul', 'south korea' => 'Asia/Seoul',
        'taiwan' => 'Asia/Taipei', 'philippines' => 'Asia/Manila', 'thailand' => 'Asia/Bangkok',
        'vietnam' => 'Asia/Ho_Chi_Minh', 'hanoi' => 'Asia/Ho_Chi_Minh', 'saigon' => 'Asia/Ho_Chi_Minh',
        'malaysia' => 'Asia/Kuala_Lumpur', 'pakistan' => 'Asia/Karachi', 'lahore' => 'Asia/Karachi',
        'bangladesh' => 'Asia/Dhaka', 'nepal' => 'Asia/Kathmandu', 'sri lanka' => 'Asia/Colombo',
        'uae' => 'Asia/Dubai', 'abu dhabi' => 'Asia/Dubai', 'saudi arabia' => 'Asia/Riyadh',
        'israel' => 'Asia/Jerusalem', 'tel aviv' => 'Asia/Jerusalem', 'iran' => 'Asia/Tehran',
        'iraq' => 'Asia/Baghdad', 'qatar' => 'Asia/Qatar', 'doha' => 'Asia/Qatar',
        // Oceania
        'new zealand' => 'Pacific/Auckland', 'wellington' => 'Pacific/Auckland',
        'canberra' => 'Australia/Sydney',
        // Africa
        'egypt' => 'Africa/Cairo', 'nigeria' => 'Africa/Lagos', 'kenya' => 'Africa/Nairobi',
        'south africa' => 'Africa/Johannesburg', 'cape town' => 'Africa/Johannesburg',
        'morocco' => 'Africa/Casablanca', 'ghana' => 'Africa/Accra', 'ethiopia' => 'Africa/Addis_Ababa',
        // Latin America
        'brazil' => 'America/Sao_Paulo', 'rio' => 'America/Sao_Paulo', 'rio de janeiro' => 'America/Sao_Paulo',
        'brasilia' => 'America/Sao_Paulo', 'argentina' => 'America/Argentina/Buenos_Aires',
        'colombia' => 'America/Bogota', 'peru' => 'America/Lima', 'chile' => 'America/Santiago',
        'venezuela' => 'America/Caracas', 'cuba' => 'America/Havana',
        'mexico' => 'America/Mexico_City', 'guadalajara' => 'America/Mexico_City',
    ];

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
        'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y',
        'ß' => 'ss', 'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
        'Ç' => 'c', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'Í' => 'i', 'Ñ' => 'n',
        'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'Ø' => 'o', 'Ú' => 'u', 'Ü' => 'u',
    ];

    /** @var array<string, string>|null lower-cased identifier => identifier */
    private static ?array $identifiers = null;

    /** @var array<string, string>|null lower-cased backward-compatible identifier ("US/Eastern", "Japan") => identifier */
    private static ?array $legacyIdentifiers = null;

    /** @var array<string, string>|null normalized last segment => identifier */
    private static ?array $segments = null;

    /**
     * @throws ParseException
     */
    public static function resolve(string|DateTimeZone $zone): DateTimeZone
    {
        if ($zone instanceof DateTimeZone) {
            return $zone;
        }

        return self::tryResolve($zone) ?? throw ParseException::cannotParse($zone, 'timezone');
    }

    public static function tryResolve(string $zone): ?DateTimeZone
    {
        $zone = trim($zone);

        if ($zone === '') {
            return null;
        }

        $identifier = self::identifierFor($zone);

        if ($identifier === null) {
            return null;
        }

        try {
            return new DateTimeZone($identifier);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Seconds to add to a wall-clock time in $from to get the wall-clock time in $to,
     * at the given instant (defaults to now, since DST makes this date-dependent).
     *
     * @throws ParseException
     */
    public static function offsetBetween(
        DateTimeZone|string $from,
        DateTimeZone|string $to,
        ?DateTimeInterface $at = null,
    ): int {
        $at ??= new DateTimeImmutable();

        return self::resolve($to)->getOffset($at) - self::resolve($from)->getOffset($at);
    }

    private static function identifierFor(string $zone): ?string
    {
        $upper = strtoupper($zone);

        if (in_array($upper, ['UTC', 'GMT', 'UT', 'Z', 'ZULU'], true)) {
            return 'UTC';
        }

        $offset = self::offsetFor($zone);

        if ($offset !== null) {
            return $offset;
        }

        if (isset(self::ABBREVIATIONS[$upper])
            && ($zone === $upper || ! in_array($upper, self::WORDLIKE_ABBREVIATIONS, true))) {
            return self::ABBREVIATIONS[$upper];
        }

        $lower = strtolower($zone);
        $normalized = self::normalize($zone);

        return self::identifiers()[$lower]
            ?? self::PLACES[$normalized]
            ?? self::legacyIdentifiers()[$lower]
            ?? self::segments()[$normalized]
            ?? null;
    }

    /**
     * Converts offset notation into the "+HH:MM" form DateTimeZone accepts. A bare offset
     * needs a two-digit hour ("+05", "-0800") so it isn't confused with a signed number.
     */
    private static function offsetFor(string $zone): ?string
    {
        $prefixed = '/^(?:UTC|GMT|UT)\s*(?<sign>[+-])\s*(?<hours>\d{1,2})(?::?(?<minutes>\d{2}))?$/i';
        $bare = '/^(?<sign>[+-])(?<hours>\d{2})(?::?(?<minutes>\d{2}))?$/';

        if (preg_match($prefixed, $zone, $matches) !== 1 && preg_match($bare, $zone, $matches) !== 1) {
            return null;
        }

        $sign = $matches['sign'];
        $hours = (int) $matches['hours'];
        $minutes = (int) ($matches['minutes'] ?? 0);

        if ($hours > 14 || $minutes > 59) {
            return null;
        }

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(strtr($value, self::ACCENTS));
        $value = str_replace(['_', '-', '.', "'"], [' ', ' ', '', ''], $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /**
     * @return array<string, string>
     */
    private static function identifiers(): array
    {
        if (self::$identifiers === null) {
            self::$identifiers = [];

            foreach (DateTimeZone::listIdentifiers() as $identifier) {
                self::$identifiers[strtolower($identifier)] = $identifier;
            }
        }

        return self::$identifiers;
    }

    /**
     * @return array<string, string>
     */
    private static function legacyIdentifiers(): array
    {
        if (self::$legacyIdentifiers === null) {
            self::$legacyIdentifiers = [];

            foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC) as $identifier) {
                self::$legacyIdentifiers[strtolower($identifier)] = $identifier;
            }
        }

        return self::$legacyIdentifiers;
    }

    /**
     * @return array<string, string>
     */
    private static function segments(): array
    {
        if (self::$segments === null) {
            self::$segments = [];

            foreach (DateTimeZone::listIdentifiers() as $identifier) {
                $parts = explode('/', $identifier);

                if (count($parts) < 2) {
                    continue;
                }

                self::$segments[self::normalize(end($parts))] ??= $identifier;
            }
        }

        return self::$segments;
    }
}
