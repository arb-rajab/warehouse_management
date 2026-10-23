<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The published and featured switches the digital-product and product lists
 * share, writing what ProductController::updatePublished()/updateFeatured()
 * write for the jQuery lists. Each list passes the products it shows as
 * `$scope`, so a product outside it is a 404, and the row is locked for the
 * write. Seller approval and today's deal use this writer too.
 */
trait UpdatesStoreProductFlags
{
    /**
     * Unpublishing also unfeatures the product and empties its stock, and
     * publishing restocks it to 1,000. A seller's product is refused
     * publication outside its shop's package, before anything is written.
     *
     * @param  Builder<Product>  $scope
     */
    private function updateProductPublished(Builder $scope, Product $product, bool $published): void
    {
        DB::transaction(function () use ($scope, $product, $published): void {
            $product = $this->lockedProduct($scope, $product);
            if ($published && $this->sellerPackageRefusesPublishing($product)) {
                throw ValidationException::withMessages(['status' => __('messages.store_product_package_limit')]);
            }
            $product->published = $published;
            $this->saveAvailability($product, $published);
        });
        $this->clearStorefrontCaches();
    }

    /**
     * @param  Builder<Product>  $scope
     */
    private function updateProductFeatured(Builder $scope, Product $product, bool $featured): void
    {
        DB::transaction(function () use ($scope, $product, $featured): void {
            $product = $this->lockedProduct($scope, $product);
            $product->setAttribute('featured', (int) $featured);
            $product->save();
        });
        $this->clearStorefrontCaches();
    }

    /**
     * Approval keeps the legacy package refusal in both directions. It
     * changes only approved, sends no notification and records no audit row.
     *
     * @param  Builder<Product>  $scope
     */
    private function writeProductApproval(Builder $scope, Product $product, bool $approved): void
    {
        DB::transaction(function () use ($scope, $product, $approved): void {
            $product = $this->lockedProduct($scope, $product);
            if ($this->sellerPackageRefusesPublishing($product)) {
                throw ValidationException::withMessages(['approved' => __('messages.store_product_package_limit')]);
            }
            $product->setAttribute('approved', (int) $approved);
            $product->save();
        });
        $this->clearStorefrontCaches();
    }

    /** @param Builder<Product> $scope */
    private function updateProductTodaysDeal(Builder $scope, Product $product, bool $todaysDeal): void
    {
        DB::transaction(function () use ($scope, $product, $todaysDeal): void {
            $product = $this->lockedProduct($scope, $product);
            $product->setAttribute('todays_deal', (int) $todaysDeal);
            $product->save();
        });
        Cache::forget('todays_deal_products');
    }

    /**
     * The bound product, locked for the surrounding transaction; anything
     * outside `$scope` is a 404.
     *
     * @param  Builder<Product>  $scope
     */
    private function lockedProduct(Builder $scope, Product $product): Product
    {
        return $scope->lockForUpdate()->findOrFail($product->id);
    }

    /**
     * Saves a locked product whose published or for-sale switch just
     * changed: switching it off unfeatures it and sets every stock row to 0
     * so nobody can order it, switching it on sets every stock row to 1,000.
     */
    private function saveAvailability(Product $product, bool $available): void
    {
        if (! $available) {
            $product->setAttribute('featured', 0);
        }
        foreach ($product->stocks()->lockForUpdate()->get() as $stock) {
            $stock->setAttribute('qty', $available ? 1000 : 0);
            $stock->save();
        }
        $product->save();
    }

    /**
     * ProductController::updatePublished()'s seller-subscription check: with
     * that addon active, a seller's shop needs a current package with room
     * for another published product.
     */
    private function sellerPackageRefusesPublishing(Product $product): bool
    {
        if ($product->getAttribute('added_by') !== 'seller' || ! addon_is_activated('seller_subscription')) {
            return false;
        }
        $shop = Shop::query()->without('user')->select(['id', 'package_invalid_at', 'product_upload_limit'])->firstWhere('user_id', $product->user_id);

        return $shop?->package_invalid_at === null
            || (int) Carbon::now()->diffInDays(Carbon::parse($shop->package_invalid_at), false) < 0
            || $shop->product_upload_limit <= Product::query()->where('user_id', $product->user_id)->where('published', 1)->count();
    }

    /**
     * The shared product switches clear the compiled views and the whole
     * cache after a write; these keep doing so.
     */
    private function clearStorefrontCaches(): void
    {
        Artisan::call('view:clear');
        Artisan::call('cache:clear');
    }
}
