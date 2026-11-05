<?php

declare(strict_types=1);

namespace Unitary\Geo\Internal;

/**
 * WGS84 ellipsoid parameters.
 *
 * @internal
 */
final class Ellipsoid
{
    public const A = 6378137.0;

    public const F = 1 / 298.257223563;

    public const B = self::A * (1 - self::F);

    /** IUGG mean Earth radius, used for spherical (haversine) calculations. */
    public const MEAN_RADIUS = 6371008.8;
}
