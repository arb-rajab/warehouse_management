<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class ProductColumnOwnership
{
    /** @var list<literal-string> */
    public const array SYNC_COLUMNS = ['name', 'ar_name', 'published'];

    /**
     * Check the database, rather than a possibly stale or partial model.
     * ProductBuilder repeats this check under a lock at the actual write.
     *
     * @param  list<string>  $columns
     */
    public static function assertStoreWrite(Product $product, array $columns): void
    {
        $protected = array_values(array_intersect($columns, self::SYNC_COLUMNS));
        if ($protected !== [] && $product->exists && $product->getConnection()->table($product->getTable())
            ->where('id', $product->getRawOriginal('id'))->whereNotNull('synced_at')->exists()) {
            self::refuse($protected);
        }
    }

    /** @param list<string> $columns */
    public static function refuse(array $columns): never
    {
        throw ValidationException::withMessages(array_fill_keys($columns, __('messages.store_product_feed_owned')));
    }
}
