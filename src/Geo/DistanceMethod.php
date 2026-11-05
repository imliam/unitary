<?php

declare(strict_types=1);

namespace Unitary\Geo;

enum DistanceMethod: string
{
    /** Vincenty's inverse formula on the WGS84 ellipsoid (sub-millimeter accuracy). */
    case Vincenty = 'vincenty';

    /** Great-circle distance on a sphere of the IUGG mean Earth radius (~0.5% error). */
    case Haversine = 'haversine';
}
