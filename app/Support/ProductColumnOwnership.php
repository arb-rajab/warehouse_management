<?php

namespace App\Support;

use App\Models\Product;
use App\Services\ProductFieldSourceService;
use Illuminate\Validation\ValidationException;

/**
 * Option A's store-write guard. The syncable columns are the registry in
 * config/product_field_sources.php; of those, the store may not change the
 * ones currently Otajer-owned (ProductFieldSourceService) on a feed-origin
 * row. An admin-owned column is the store's on every row.
 */
final class ProductColumnOwnership
{
    /**
     * Every column products:sync can write, whoever owns it at the moment.
     *
     * @return list<string>
     */
    public static function syncColumns(): array
    {
        return app(ProductFieldSourceService::class)->fields();
    }

    /**
     * The subset of `$columns` the store may not change on a feed-origin row.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function protectedColumns(array $columns): array
    {
        $syncable = array_values(array_intersect($columns, self::syncColumns()));

        return $syncable === []
            ? []
            : array_values(array_intersect($syncable, app(ProductFieldSourceService::class)->otajerOwnedFields()));
    }

    /**
     * Check the database, rather than a possibly stale or partial model.
     * ProductBuilder repeats this check under a lock at the actual write.
     *
     * @param  list<string>  $columns
     */
    public static function assertStoreWrite(Product $product, array $columns): void
    {
        if (! $product->exists) {
            return;
        }
        $protected = self::protectedColumns($columns);
        if ($protected !== [] && $product->getConnection()->table($product->getTable())
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
