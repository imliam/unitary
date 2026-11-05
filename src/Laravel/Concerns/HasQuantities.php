<?php

declare(strict_types=1);

namespace Unitary\Laravel\Concerns;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Unitary\Laravel\Casts\QuantityStorage;

/**
 * Query scopes for attributes cast with QuantityCast or QuantityWithUnitCast.
 * Operands are converted to however the column is stored:
 *
 *     Parcel::whereQuantity('weight', '>', '5 lb')->orderByQuantity('weight')->get();
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasQuantities
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereQuantity(Builder $query, string $attribute, mixed $operator, mixed $value = null, string $boolean = 'and'): Builder
    {
        if (func_num_args() === 3) {
            [$operator, $value] = ['=', $operator];
        }

        if (! is_string($operator)) {
            throw new InvalidArgumentException('The comparison operator must be a string');
        }

        $storage = $this->quantityStorage($attribute);

        return $query->where($query->qualifyColumn($storage->valueColumn($attribute)), $operator, $storage->toStoredValue($value), $boolean);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrWhereQuantity(Builder $query, string $attribute, mixed $operator, mixed $value = null): Builder
    {
        if (func_num_args() === 3) {
            [$operator, $value] = ['=', $operator];
        }

        return $this->scopeWhereQuantity($query, $attribute, $operator, $value, 'or');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereQuantityBetween(Builder $query, string $attribute, mixed $from, mixed $to): Builder
    {
        $storage = $this->quantityStorage($attribute);

        $query->whereBetween($query->qualifyColumn($storage->valueColumn($attribute)), [
            $storage->toStoredValue($from),
            $storage->toStoredValue($to),
        ]);

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrderByQuantity(Builder $query, string $attribute, string $direction = 'asc'): Builder
    {
        $query->orderBy(
            $query->qualifyColumn($this->quantityStorage($attribute)->valueColumn($attribute)),
            strtolower($direction) === 'desc' ? 'desc' : 'asc',
        );

        return $query;
    }

    protected function quantityStorage(string $attribute): QuantityStorage
    {
        $cast = $this->getCasts()[$attribute] ?? null;

        if (! is_string($cast)) {
            throw new InvalidArgumentException(sprintf('The %s attribute of %s has no quantity cast', $attribute, static::class));
        }

        [$class, $arguments] = array_pad(explode(':', $cast, 2), 2, '');

        if (! is_a($class, QuantityStorage::class, true)) {
            throw new InvalidArgumentException(sprintf('The %s attribute of %s is not cast to a quantity', $attribute, static::class));
        }

        return new $class(...($arguments === '' ? [] : explode(',', $arguments)));
    }
}
