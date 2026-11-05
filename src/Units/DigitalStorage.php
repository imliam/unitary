<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: bit. Decimal prefixes are powers of 1000 (kB = 1000 B); binary prefixes are powers of 1024 (KiB = 1024 B). "KB" is treated as kilobyte (1000 B).
 */
enum DigitalStorage: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case Bit = 'bit';
    case Nibble = 'nibble';
    case Byte = 'B';
    case Kilobit = 'kbit';
    case Megabit = 'Mbit';
    case Gigabit = 'Gbit';
    case Terabit = 'Tbit';
    case Kilobyte = 'kB';
    case Megabyte = 'MB';
    case Gigabyte = 'GB';
    case Terabyte = 'TB';
    case Petabyte = 'PB';
    case Exabyte = 'EB';
    case Kibibyte = 'KiB';
    case Mebibyte = 'MiB';
    case Gibibyte = 'GiB';
    case Tebibyte = 'TiB';
    case Pebibyte = 'PiB';
    case Kibibit = 'Kibit';
    case Mebibit = 'Mibit';
    case Gibibit = 'Gibit';

    public function dimension(): Dimension
    {
        return Dimension::DigitalStorage;
    }

    protected function define(): U
    {
        return match ($this) {
            self::Bit => U::of('bit')->prefixable(binary: true),
            self::Nibble => U::of('nibble', 4)->aliases('nybble', 'nybbles'),
            self::Byte => U::of('byte', 8)->prefixable(binary: true)->aliases('octet', 'octets'),
            self::Kilobit => U::of('kilobit', 1000)->aliases('kb', 'Kb', 'Kbit'),
            self::Megabit => U::of('megabit', '10^6')->aliases('Mb'),
            self::Gigabit => U::of('gigabit', '10^9')->aliases('Gb'),
            self::Terabit => U::of('terabit', '10^12')->aliases('Tb'),
            self::Kilobyte => U::of('kilobyte', 8000)->aliases('KB'),
            self::Megabyte => U::of('megabyte', '8 * 10^6'),
            self::Gigabyte => U::of('gigabyte', '8 * 10^9'),
            self::Terabyte => U::of('terabyte', '8 * 10^12'),
            self::Petabyte => U::of('petabyte', '8 * 10^15'),
            self::Exabyte => U::of('exabyte', '8 * 10^18'),
            self::Kibibyte => U::of('kibibyte', '8 * 2^10'),
            self::Mebibyte => U::of('mebibyte', '8 * 2^20'),
            self::Gibibyte => U::of('gibibyte', '8 * 2^30'),
            self::Tebibyte => U::of('tebibyte', '8 * 2^40'),
            self::Pebibyte => U::of('pebibyte', '8 * 2^50'),
            self::Kibibit => U::of('kibibit', '2^10'),
            self::Mebibit => U::of('mebibit', '2^20'),
            self::Gibibit => U::of('gibibit', '2^30'),
        };
    }
}
