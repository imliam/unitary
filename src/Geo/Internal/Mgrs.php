<?php

declare(strict_types=1);

namespace Unitary\Geo\Internal;

use InvalidArgumentException;

/**
 * Military Grid Reference System (NGA), built on UTM. Polar UPS regions are not supported.
 *
 * @internal
 */
final class Mgrs
{
    /** 100 km column letters, repeating every three zones. */
    private const COLUMNS = ['ABCDEFGH', 'JKLMNPQR', 'STUVWXYZ'];

    /** 100 km row letters, alternating between odd and even zones. */
    private const ROWS = ['ABCDEFGHJKLMNPQRSTUV', 'FGHJKLMNPQRSTUVABCDE'];

    /**
     * @return array{zone: int, band: string, column: string, row: string, easting: int, northing: int}
     */
    public static function fromLatLng(float $lat, float $lng, int $precision): array
    {
        $utm = Utm::fromLatLng($lat, $lng);
        $zone = $utm['zone'];

        // MGRS truncates to the containing square; the micrometer of slack stops
        // floating-point noise from a decoded corner (699315.9999…) dropping a meter.
        $easting = (int) floor($utm['easting'] + 1e-6);
        $northing = (int) floor($utm['northing'] + 1e-6);

        $column = self::COLUMNS[($zone - 1) % 3][intdiv($easting, 100000) - 1];
        $row = self::ROWS[($zone - 1) % 2][intdiv($northing, 100000) % 20];
        $divisor = 10 ** (5 - $precision);

        return [
            'zone' => $zone,
            'band' => $utm['band'],
            'column' => $column,
            'row' => $row,
            'easting' => intdiv($easting % 100000, $divisor),
            'northing' => intdiv($northing % 100000, $divisor),
        ];
    }

    /**
     * @return array{float, float} latitude and longitude of the square's south-west corner
     */
    public static function toLatLng(int $zone, string $band, string $column, string $row, string $easting, string $northing): array
    {
        $band = strtoupper($band);
        $bandIndex = strpos(Utm::BANDS, $band);

        if ($zone < 1 || $zone > 60 || $bandIndex === false || strlen($easting) !== strlen($northing) || strlen($easting) > 5) {
            throw new InvalidArgumentException('Invalid MGRS reference');
        }

        $columnIndex = strpos(self::COLUMNS[($zone - 1) % 3], strtoupper($column));
        $rowIndex = strpos(self::ROWS[($zone - 1) % 2], strtoupper($row));

        if ($columnIndex === false || $rowIndex === false) {
            throw new InvalidArgumentException('Invalid MGRS 100 km square');
        }

        $scale = 10 ** (5 - strlen($easting));
        $e = ($columnIndex + 1) * 100000 + (int) $easting * $scale;
        $n = $rowIndex * 100000 + (int) $northing * $scale;

        // Rows repeat every 2,000 km: add multiples until we reach the latitude band.
        $bandLatitude = ($bandIndex - 10) * 8;
        $bandNorthing = floor(Utm::project($bandLatitude, Utm::centralMeridian($zone), $zone)[1] / 100000) * 100000;
        $northern = $band >= 'N';

        if (! $northern && $bandLatitude === -80) {
            $bandNorthing = floor(Utm::project(-80, Utm::centralMeridian($zone), $zone)[1] / 100000) * 100000;
        }

        while ($n < $bandNorthing) {
            $n += 2000000;
        }

        return Utm::toLatLng($zone, $northern, (float) $e, (float) $n);
    }
}
