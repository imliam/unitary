<?php

declare(strict_types=1);

namespace Unitary\Geo;

use Unitary\Quantity;

/**
 * Distances between coordinates written as text, e.g.
 * "distance from 51.5072,-0.1276 to 48.8566,2.3522" or "51.5072,-0.1276 to 48.8566,2.3522".
 */
final class Distance
{
    private const SEPARATORS = '/\s+(?:to|and)\s+|\s*(?:->|→|↔)\s*/iu';

    /**
     * @return \Unitary\Quantity<\Unitary\Unit>
     */
    public static function between(Coordinate $from, Coordinate $to, DistanceMethod $method = DistanceMethod::Vincenty): Quantity
    {
        return $from->distanceTo($to, $method);
    }

    /** The distance in meters, or null when the text isn't two coordinates. */
    /**
     * @return ?\Unitary\Quantity<\Unitary\Unit>
     */
    public static function tryParse(string $input, DistanceMethod $method = DistanceMethod::Vincenty): ?Quantity
    {
        $text = trim((string) preg_replace('/^(?:(?:the\s+)?distance\s+)?(?:from|between)\s+|^(?:the\s+)?distance\s+/i', '', trim($input)));

        if (! preg_match_all(self::SEPARATORS, $text, $separators, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return null;
        }

        foreach ($separators as [[$separator, $offset]]) {
            $from = Coordinate::tryParse(substr($text, 0, $offset));
            $to = $from !== null ? Coordinate::tryParse(substr($text, $offset + strlen($separator))) : null;

            if ($from !== null && $to !== null) {
                return $from->distanceTo($to, $method);
            }
        }

        return null;
    }
}
