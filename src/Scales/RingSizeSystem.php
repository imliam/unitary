<?php

declare(strict_types=1);

namespace Unitary\Scales;

use Unitary\Exceptions\ParseException;

enum RingSizeSystem: string
{
    /** US/Canada: inner diameter = 0.458 in + 0.032 in per size, in quarter sizes. */
    case US = 'US';

    /** UK/Australia/Ireland (BS 6820): letters, A = 37.5 mm inner circumference, +1.25 mm per letter, with halves. */
    case UK = 'UK';

    /** ISO 8653 (EU): inner circumference in millimeters. */
    case ISO = 'ISO';

    public static function fromName(string $name): self
    {
        return self::tryFromName($name) ?? throw ParseException::cannotParse($name, 'ring size system');
    }

    public static function tryFromName(string $name): ?self
    {
        return match (strtolower(trim($name, " .\t"))) {
            'us', 'usa', 'ca', 'canada', 'american' => self::US,
            'uk', 'gb', 'british', 'au', 'aus', 'australia', 'ie', 'ireland' => self::UK,
            'iso', 'eu', 'eur', 'euro', 'european', 'de', 'fr' => self::ISO,
            default => null,
        };
    }
}
