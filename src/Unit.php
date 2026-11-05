<?php

declare(strict_types=1);

namespace Unitary;

/**
 * Anything a quantity can be expressed in: a measurement unit or a currency.
 */
interface Unit
{
    public function dimension(): Dimension;

    /** The canonical symbol, e.g. "km", "°C" or "GBP". */
    public function symbol(): string;

    /** The singular English name, e.g. "kilometer". */
    public function name(): string;

    /**
     * Every string (other than the symbol) that should resolve to this unit
     * when parsing freeform input, including plurals and alternative spellings.
     *
     * @return list<string>
     */
    public function aliases(): array;
}
