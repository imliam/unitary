<?php

declare(strict_types=1);

namespace Unitary\Laravel\Casts;

/**
 * Shared by the quantity casts so query helpers know how a value is stored.
 */
interface QuantityStorage
{
    /** The database column holding the numeric value for the given attribute. */
    public function valueColumn(string $attribute): string;

    /**
     * Converts a comparison operand ("5 lb", a Quantity, or a number in the
     * stored unit) into the number stored in valueColumn().
     */
    public function toStoredValue(mixed $value): string;
}
