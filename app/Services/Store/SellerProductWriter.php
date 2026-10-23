<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\StoreUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Explicit seller writes; ownership and approval are server decisions. */
class SellerProductWriter extends PhysicalProductWriter
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $names
     */
    public function create(StoreUser $seller, array $data, array $names): Product
    {
        return DB::transaction(function () use ($seller, $data, $names): Product {
            $product = new Product;
            $product->forceFill([
                ...$this->postedColumns($data), ...$this->derivedColumns($data),
                ...$this->variantColumns($data, $names, ($data['colors_active'] ?? false) ? ($data['colors'] ?? []) : []),
                'barcode' => $data['barcode'] ?? null,
                'added_by' => 'seller', 'user_id' => $seller->id,
                'approved' => get_setting('product_approve_by_admin') == 1 ? 0 : 1,
                'digital' => 0, 'published' => in_array($data['button'] ?? null, ['unpublish', 'draft'], true) ? 0 : 1,
                'slug' => $this->numberedSlug(Str::slug($data['name']), null),
                'shipping_cost' => ($data['shipping_type'] ?? null) === 'flat_rate' ? ($data['flat_shipping_cost'] ?? 0) : 0,
                'cash_on_delivery' => (int) ($data['cash_on_delivery'] ?? false),
            ])->save();
            $this->syncStocks($product, $this->stockRows($data, $names));
            $this->saveTaxes($product, $data, false);
            $this->saveTranslation($product, config('app.default_language'), $data);
            $this->clearAfterCommit();

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $names
     */
    public function update(StoreUser $seller, Product $product, array $data, array $names): Product
    {
        return DB::transaction(function () use ($seller, $product, $data, $names): Product {
            $product = Product::query()->where('user_id', $seller->id)->lockForUpdate()->findOrFail($product->id);
            Gate::forUser($seller)->authorize('updateAsSeller', $product);
            app(ProductVariantCombinations::class)->assertLegacyStocksRetained($product, $names);
            $columns = $this->postedColumns($data);
            if ($data['lang'] !== config('app.default_language')) {
                unset($columns['name'], $columns['unit'], $columns['description']);
            }
            $product->forceFill([
                ...$columns, ...$this->derivedColumns($data),
                ...$this->variantColumns($data, $names, ($data['colors_active'] ?? false) ? ($data['colors'] ?? []) : []),
                'barcode' => $data['barcode'] ?? null,
                'cash_on_delivery' => (int) ($data['cash_on_delivery'] ?? false),
            ]);
            if (array_key_exists('shipping_type', $data)) {
                $product->setAttribute('shipping_cost', $data['shipping_type'] === 'flat_rate' ? ($data['flat_shipping_cost'] ?? 0) : 0);
            }
            $product->slug = $data['slug'] ?? $product->getAttribute('slug') ?? $this->numberedSlug(Str::slug($data['name']), $product->id);
            $product->save();
            $this->syncStocks($product, $this->stockRows($data, $names));
            $this->saveTaxes($product, $data, true);
            $this->saveTranslation($product, $data['lang'], $data);
            $this->clearAfterCommit();

            return $product;
        });
    }

    private function clearAfterCommit(): void
    {
        DB::afterCommit(function (): void {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
        });
    }
}
