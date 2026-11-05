<?php

declare(strict_types=1);

namespace Unitary;

/**
 * How a unit is written next to its value, like Intl.NumberFormat's unitDisplay:
 * 2″, 2 in or 2 inches. With a locale and ext-intl, the words come from CLDR
 * in that language ("2 pouces").
 */
enum UnitDisplay: string
{
    /** As short as the language allows: 2″, 20°, £5. */
    case Narrow = 'narrow';

    /** The usual abbreviation: 2 in, 20°C, £5.00. */
    case Short = 'short';

    /** Spelled out and pluralised: 2 inches, 20 degrees Celsius, 5.00 British pounds. */
    case Long = 'long';
}
