<?php

declare(strict_types=1);

namespace Unitary\Units\Novelty;

use Unitary\Concerns\IsMeasurementUnit;
use Unitary\Dimension;
use Unitary\MeasurementUnit;
use Unitary\UnitDefinition as U;

/**
 * Dungeons & Dragons coinage, per the 5th edition Player's Handbook ("Coins"):
 * 1 pp = 10 gp, 1 gp = 2 ep = 10 sp = 100 cp. Base unit: copper piece.
 *
 * It's its own dimension, so gold pieces never convert to real currencies.
 * "cp" and "sp" also mean candlepower and spat; the coin reading is used
 * whenever the other side of a conversion is coinage ("500 cp in gp"). Likewise
 * "gold" and "silver" mean the commodity currencies (XAU, XAG) unless coins are involved.
 */
enum DndCurrency: string implements MeasurementUnit
{
    use IsMeasurementUnit;

    case CopperPiece = 'cp';
    case SilverPiece = 'sp';
    case ElectrumPiece = 'ep';
    case GoldPiece = 'gp';
    case PlatinumPiece = 'pp';

    public function dimension(): Dimension
    {
        return Dimension::FantasyCurrency;
    }

    protected function define(): U
    {
        return match ($this) {
            self::CopperPiece => U::of('copper piece', 1)->aliases('copper', 'coppers', 'copper coin', 'copper coins'),
            self::SilverPiece => U::of('silver piece', 10)->aliases('silver', 'silver coin', 'silver coins'),
            self::ElectrumPiece => U::of('electrum piece', 50)->aliases('electrum', 'electrum coin', 'electrum coins'),
            self::GoldPiece => U::of('gold piece', 100)->aliases('gold', 'gold coin', 'gold coins', 'GP'),
            self::PlatinumPiece => U::of('platinum piece', 1000)->aliases('platinum', 'platinum coin', 'platinum coins', 'PP'),
        };
    }
}
