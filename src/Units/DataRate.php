<?php

declare(strict_types=1);

namespace Unitary\Units;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Base unit: bit per second.
 */
enum DataRate: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case BitPerSecond = 'bit/s';
    case KilobitPerSecond = 'kbit/s';
    case MegabitPerSecond = 'Mbit/s';
    case GigabitPerSecond = 'Gbit/s';
    case TerabitPerSecond = 'Tbit/s';
    case BytePerSecond = 'B/s';
    case KilobytePerSecond = 'kB/s';
    case MegabytePerSecond = 'MB/s';
    case GigabytePerSecond = 'GB/s';
    case MebibytePerSecond = 'MiB/s';
    case GibibytePerSecond = 'GiB/s';

    public function dimension(): Dimension
    {
        return Dimension::DataRate;
    }

    protected function define(): U
    {
        return match ($this) {
            self::BitPerSecond => U::of('bit per second')->prefixable(binary: true)->aliases('bps', 'b/s'),
            self::KilobitPerSecond => U::of('kilobit per second', 1000)->aliases('kbps', 'Kbps', 'kb/s', 'Kb/s'),
            self::MegabitPerSecond => U::of('megabit per second', '10^6')->aliases('Mbps', 'mbps', 'Mb/s'),
            self::GigabitPerSecond => U::of('gigabit per second', '10^9')->aliases('Gbps', 'gbps', 'Gb/s'),
            self::TerabitPerSecond => U::of('terabit per second', '10^12')->aliases('Tbps', 'Tb/s'),
            self::BytePerSecond => U::of('byte per second', 8)->prefixable(binary: true)->aliases('Bps'),
            self::KilobytePerSecond => U::of('kilobyte per second', 8000)->aliases('KB/s', 'kBps', 'KBps'),
            self::MegabytePerSecond => U::of('megabyte per second', '8 * 10^6')->aliases('MBps'),
            self::GigabytePerSecond => U::of('gigabyte per second', '8 * 10^9')->aliases('GBps'),
            self::MebibytePerSecond => U::of('mebibyte per second', '8 * 2^20'),
            self::GibibytePerSecond => U::of('gibibyte per second', '8 * 2^30'),
        };
    }
}
