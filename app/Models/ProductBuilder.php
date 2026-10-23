<?php

namespace App\Models;

use App\Support\ProductColumnOwnership;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Guards model saves (including quiet saves) and bulk Eloquent updates alike.
 * The sync alone uses the base database builder for its conditional upsert.
 *
 * @template TModel of Product
 *
 * @extends Builder<TModel>
 */
class ProductBuilder extends Builder
{
    public function update(array $values): int
    {
        return $this->storeWrite($values, fn (): int => parent::update($values));
    }

    public function increment($column, $amount = 1, array $extra = []): int
    {
        if (! is_string($column)) {
            ProductColumnOwnership::refuse(ProductColumnOwnership::SYNC_COLUMNS);
        }

        return $this->storeWrite([$column => $amount, ...$extra], fn (): int => parent::increment($column, $amount, $extra));
    }

    public function decrement($column, $amount = 1, array $extra = []): int
    {
        if (! is_string($column)) {
            ProductColumnOwnership::refuse(ProductColumnOwnership::SYNC_COLUMNS);
        }

        return $this->storeWrite([$column => $amount, ...$extra], fn (): int => parent::decrement($column, $amount, $extra));
    }

    public function incrementEach(array $columns, array $extra = []): int
    {
        return $this->storeWrite([...$columns, ...$extra], fn (): int => parent::incrementEach($columns, $extra));
    }

    public function decrementEach(array $columns, array $extra = []): int
    {
        return $this->storeWrite([...$columns, ...$extra], fn (): int => parent::decrementEach($columns, $extra));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>|string  $uniqueBy
     * @param  list<string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        ProductColumnOwnership::refuse(['synced_at']);
    }

    /**
     * Refuse the whole write before changing any row in a mixed-origin set.
     *
     * @param  array<string, mixed>  $values
     * @param  Closure(): int  $write
     */
    private function storeWrite(array $values, Closure $write): int
    {
        $columns = array_map(fn (string $column): string => last(explode('.', $column)), array_keys($values));
        if (in_array('synced_at', $columns, true)) {
            ProductColumnOwnership::refuse(['synced_at']);
        }
        $protected = array_values(array_intersect($columns, ProductColumnOwnership::SYNC_COLUMNS));
        if ($protected === []) {
            return $write();
        }

        return $this->getConnection()->transaction(function () use ($protected, $write): int {
            $rows = (clone $this)->reorder()->lockForUpdate()->get(['products.id', 'products.synced_at']);
            if ($rows->contains(fn (Product $product): bool => $product->getRawOriginal('synced_at') !== null)) {
                ProductColumnOwnership::refuse($protected);
            }

            return $write();
        });
    }
}
