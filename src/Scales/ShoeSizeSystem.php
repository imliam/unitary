<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Unitary\Exceptions\ParseException;

enum ShoeSizeSystem: string
{
    /** Barleycorn scale: adult size = 3 × last length (in) − 25; children's = 3 × last − 12. */
    case UK = 'UK';

    /** Men's = 3 × last length (in) − 24; women's = 3 × last − 22.5; children's = 3 × last − 11⅔. */
    case US = 'US';

    /** Paris point (⅔ cm): size = 1.5 × last length (cm). */
    case EU = 'EU';

    /** ISO 9407: foot length in millimeters. */
    case Mondopoint = 'Mondopoint';

    /** JIS S 5037: foot length in centimeters. */
    case JP = 'JP';

    public static function fromName(string $name): self
    {
        return self::tryFromName($name) ?? throw ParseException::cannotParse($name, 'shoe size system');
    }

    public static function tryFromName(string $name): ?self
    {
        return match (strtolower(trim($name, " .\t"))) {
            'uk', 'gb', 'british', 'au', 'aus' => self::UK,
            'us', 'usa', 'american', 'ca', 'canada' => self::US,
            'eu', 'eur', 'euro', 'european', 'continental', 'fr', 'de' => self::EU,
            'mondopoint', 'mondo', 'mp' => self::Mondopoint,
            'jp', 'japan', 'japanese', 'cn', 'kr' => self::JP,
            default => null,
        };
    }
}
