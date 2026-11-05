<?php

declare(strict_types=1);

namespace Unitary\Geo;

use JsonSerializable;
use Stringable;
use Unitary\Exceptions\ParseException;
use Unitary\Geo\Internal\CoordinateParser;
use Unitary\Geo\Internal\Geohash;
use Unitary\Geo\Internal\Mgrs;
use Unitary\Geo\Internal\PlusCode;
use Unitary\Geo\Internal\Utm;
use Unitary\Geo\Internal\Vincenty;
use Unitary\Quantity;
use Unitary\Units\Angle;
use Unitary\Units\Length;

/**
 * A WGS84 position. The format only affects how it's displayed: convert with
 * in() to keep a Coordinate, or to() to get the formatted string directly.
 */
final readonly class Coordinate implements JsonSerializable, Stringable
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public CoordinateFormat $format = CoordinateFormat::Decimal,
    ) {
        if (! is_finite($latitude) || abs($latitude) > 90) {
            throw ParseException::cannotParse("{$latitude}, {$longitude}", 'coordinate', 'latitude must be between -90 and 90');
        }

        if (! is_finite($longitude) || abs($longitude) > 180) {
            throw ParseException::cannotParse("{$latitude}, {$longitude}", 'coordinate', 'longitude must be between -180 and 180');
        }
    }

    public static function of(float $latitude, float $longitude): self
    {
        return new self($latitude, $longitude);
    }

    /**
     * Parses decimal degrees ("51.5072, -0.1276", "51.5072°N 0.1276°W"), DMS
     * ("51°30′26″N 0°7′39″W"), DDM, UTM ("30U 699316 5710164"), MGRS
     * ("30UXC9931610164"), plus codes ("9C3XGV4C+XV") and "geohash:gcpvj0duq".
     *
     * @throws ParseException
     */
    public static function parse(string $input): self
    {
        return self::tryParse($input) ?? throw ParseException::cannotParse($input, 'coordinate');
    }

    public static function tryParse(string $input): ?self
    {
        $position = CoordinateParser::parse($input);

        return $position === null ? null : new self(...$position);
    }

    public static function fromGeohash(string $geohash): self
    {
        if (! Geohash::isValid($geohash)) {
            throw ParseException::cannotParse($geohash, 'geohash');
        }

        [$latitude, $longitude] = Geohash::decode($geohash);

        return new self($latitude, $longitude, CoordinateFormat::Geohash);
    }

    public static function fromPlusCode(string $code): self
    {
        if (! PlusCode::isValidFull($code)) {
            throw ParseException::cannotParse($code, 'plus code', 'only full codes are supported');
        }

        [$latitude, $longitude] = PlusCode::decode($code);

        return new self($latitude, $longitude, CoordinateFormat::PlusCode);
    }

    /**
     * @param  string|bool  $bandOrNorthern  A latitude band letter ("U") or true for the northern hemisphere.
     */
    public static function fromUtm(int $zone, string|bool $bandOrNorthern, float $easting, float $northing): self
    {
        $northern = is_bool($bandOrNorthern) ? $bandOrNorthern : strtoupper($bandOrNorthern) >= 'N';

        try {
            [$latitude, $longitude] = Utm::toLatLng($zone, $northern, $easting, $northing);
        } catch (\InvalidArgumentException $e) {
            throw ParseException::cannotParse("{$zone} {$easting} {$northing}", 'UTM coordinate', $e->getMessage());
        }

        return new self($latitude, $longitude, CoordinateFormat::Utm);
    }

    public function in(CoordinateFormat|string $format): self
    {
        return new self($this->latitude, $this->longitude, self::format($format));
    }

    public function to(CoordinateFormat|string $format): string
    {
        return match (self::format($format)) {
            CoordinateFormat::Decimal => $this->toDecimal(),
            CoordinateFormat::Dms => $this->toDms(),
            CoordinateFormat::Ddm => $this->toDdm(),
            CoordinateFormat::Utm => $this->toUtm(),
            CoordinateFormat::Mgrs => $this->toMgrs(),
            CoordinateFormat::Geohash => $this->toGeohash(),
            CoordinateFormat::PlusCode => $this->toPlusCode(),
        };
    }

    /** "51.5072, -0.1276" */
    public function toDecimal(int $precision = 6): string
    {
        return self::trimmed($this->latitude, $precision).', '.self::trimmed($this->longitude, $precision);
    }

    /** "51°30′26″N 0°07′39″W" */
    public function toDms(int $secondsPrecision = 0): string
    {
        return self::dms($this->latitude, 'N', 'S', $secondsPrecision).' '.self::dms($this->longitude, 'E', 'W', $secondsPrecision);
    }

    /** "51°30.432′N 0°07.656′W" */
    public function toDdm(int $minutesPrecision = 3): string
    {
        return self::ddm($this->latitude, 'N', 'S', $minutesPrecision).' '.self::ddm($this->longitude, 'E', 'W', $minutesPrecision);
    }

    /** "30U 699316 5710164" (zone, latitude band, easting and northing in meters) */
    public function toUtm(): string
    {
        $utm = $this->utm();

        return sprintf('%d%s %d %d', $utm['zone'], $utm['band'], round($utm['easting']), round($utm['northing']));
    }

    /** "30U XC 99316 10164"; precision is digits per axis, from 0 (100 km) to 5 (1 m). */
    public function toMgrs(int $precision = 5): string
    {
        $precision = max(0, min(5, $precision));

        try {
            $mgrs = Mgrs::fromLatLng($this->latitude, $this->longitude, $precision);
        } catch (\InvalidArgumentException $e) {
            throw ParseException::cannotParse($this->toDecimal(), 'MGRS coordinate', $e->getMessage());
        }

        $square = "{$mgrs['zone']}{$mgrs['band']} {$mgrs['column']}{$mgrs['row']}";

        return $precision === 0
            ? $square
            : sprintf("%s %0{$precision}d %0{$precision}d", $square, $mgrs['easting'], $mgrs['northing']);
    }

    public function toGeohash(int $length = 9): string
    {
        return Geohash::encode($this->latitude, $this->longitude, max(1, min(22, $length)));
    }

    public function toPlusCode(int $length = 10): string
    {
        try {
            return PlusCode::encode($this->latitude, $this->longitude, $length);
        } catch (\InvalidArgumentException $e) {
            throw ParseException::cannotParse((string) $length, 'plus code length', $e->getMessage());
        }
    }

    /** The geodesic distance in meters. */
    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function distanceTo(self $other, DistanceMethod $method = DistanceMethod::Vincenty): Quantity
    {
        $meters = $method === DistanceMethod::Vincenty
            ? Vincenty::inverse($this->latitude, $this->longitude, $other->latitude, $other->longitude)['distance'] ?? null
            : null;

        $meters ??= Vincenty::haversine($this->latitude, $this->longitude, $other->latitude, $other->longitude);

        return new Quantity($meters, Length::Meter);
    }

    /** The initial bearing towards another coordinate, in degrees clockwise from true north. */
    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public function bearingTo(self $other): Quantity
    {
        $degrees = Vincenty::inverse($this->latitude, $this->longitude, $other->latitude, $other->longitude)['bearing']
            ?? Vincenty::sphericalBearing($this->latitude, $this->longitude, $other->latitude, $other->longitude);

        return new Quantity($degrees, Angle::Degree);
    }

    public function toString(): string
    {
        return $this->to($this->format);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function jsonSerialize(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }

    /**
     * @return array{zone: int, band: string, easting: float, northing: float}
     */
    private function utm(): array
    {
        try {
            return Utm::fromLatLng($this->latitude, $this->longitude);
        } catch (\InvalidArgumentException $e) {
            throw ParseException::cannotParse($this->toDecimal(), 'UTM coordinate', $e->getMessage());
        }
    }

    private static function format(CoordinateFormat|string $format): CoordinateFormat
    {
        return $format instanceof CoordinateFormat ? $format : CoordinateFormat::fromName($format);
    }

    private static function trimmed(float $value, int $precision): string
    {
        $string = rtrim(rtrim(number_format($value, max(0, $precision), '.', ''), '0'), '.');

        return $string === '-0' ? '0' : $string;
    }

    private static function dms(float $value, string $positive, string $negative, int $precision): string
    {
        $precision = max(0, $precision);
        $total = round(abs($value) * 3600, $precision);
        $degrees = (int) floor($total / 3600);
        $minutes = (int) floor(($total - $degrees * 3600) / 60);
        $seconds = $total - $degrees * 3600 - $minutes * 60;
        $width = $precision > 0 ? $precision + 3 : 2;

        return sprintf("%d°%02d′%0{$width}.{$precision}f″%s", $degrees, $minutes, $seconds, $value < 0 ? $negative : $positive);
    }

    private static function ddm(float $value, string $positive, string $negative, int $precision): string
    {
        $precision = max(0, $precision);
        $total = round(abs($value) * 60, $precision);
        $degrees = (int) floor($total / 60);
        $minutes = $total - $degrees * 60;
        $width = $precision > 0 ? $precision + 3 : 2;

        return sprintf("%d°%0{$width}.{$precision}f′%s", $degrees, $minutes, $value < 0 ? $negative : $positive);
    }
}
