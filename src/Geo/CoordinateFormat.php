<?php

declare(strict_types=1);

namespace Unitary\Geo;

use Unitary\Exceptions\ParseException;

enum CoordinateFormat: string
{
    case Decimal = 'decimal';
    case Dms = 'dms';
    case Ddm = 'ddm';
    case Utm = 'utm';
    case Mgrs = 'mgrs';
    case Geohash = 'geohash';
    case PlusCode = 'pluscode';

    private const ALIASES = [
        'decimal' => self::Decimal, 'dd' => self::Decimal, 'decimal degrees' => self::Decimal, 'degrees' => self::Decimal, 'latlng' => self::Decimal, 'lat lng' => self::Decimal, 'lat/lng' => self::Decimal, 'latlon' => self::Decimal,
        'dms' => self::Dms, 'degrees minutes seconds' => self::Dms, 'degrees, minutes, seconds' => self::Dms, 'sexagesimal' => self::Dms,
        'ddm' => self::Ddm, 'dm' => self::Ddm, 'degrees decimal minutes' => self::Ddm, 'degrees minutes' => self::Ddm,
        'utm' => self::Utm, 'universal transverse mercator' => self::Utm,
        'mgrs' => self::Mgrs, 'military grid reference system' => self::Mgrs, 'military grid' => self::Mgrs, 'usng' => self::Mgrs,
        'geohash' => self::Geohash, 'geo hash' => self::Geohash, 'geo-hash' => self::Geohash,
        'pluscode' => self::PlusCode, 'plus code' => self::PlusCode, 'plus-code' => self::PlusCode, 'olc' => self::PlusCode, 'open location code' => self::PlusCode,
    ];

    public static function fromName(string $name): self
    {
        return self::tryFromName($name) ?? throw ParseException::cannotParse($name, 'coordinate format');
    }

    public static function tryFromName(string $name): ?self
    {
        $key = (string) preg_replace('/\s+/', ' ', strtolower(trim($name)));

        return self::ALIASES[$key] ?? null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Decimal => 'decimal degrees',
            self::Dms => 'degrees, minutes and seconds',
            self::Ddm => 'degrees and decimal minutes',
            self::Utm => 'UTM',
            self::Mgrs => 'MGRS',
            self::Geohash => 'geohash',
            self::PlusCode => 'plus code',
        };
    }
}
