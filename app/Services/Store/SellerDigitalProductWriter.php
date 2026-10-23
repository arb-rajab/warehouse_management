<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\StoreUser;
use App\Support\ProductSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SellerDigitalProductWriter extends ProductWriter
{
    /** @param array<string, mixed> $data */
    public function create(StoreUser $seller, array $data): Product
    {
        return DB::transaction(function () use ($seller, $data): Product {
            $product = new Product;
            $product->forceFill([
                ...$this->columns($data), 'added_by' => 'seller', 'user_id' => $seller->id, 'digital' => 1,
                'approved' => get_setting('product_approve_by_admin') == 1 ? 0 : 1, 'published' => 1,
                'slug' => ProductSlug::numbered(Str::slug($data['name']), null),
            ])->save();
            $this->saveRelated($product, $data, config('app.default_language'), false);

            return $product;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(StoreUser $seller, Product $product, array $data): Product
    {
        return DB::transaction(function () use ($seller, $product, $data): Product {
            $product = Product::query()->where('user_id', $seller->id)->lockForUpdate()->findOrFail($product->id);
            Gate::forUser($seller)->authorize('updateAsSeller', $product);
            $columns = $this->columns($data);
            if ($data['lang'] !== config('app.default_language')) {
                unset($columns['name'], $columns['description']);
            }
            $product->forceFill($columns);
            $product->slug = $data['slug'] ?? $product->slug ?? ProductSlug::numbered(Str::slug($data['name']), $product->id);
            $product->save();
            $this->saveRelated($product, $data, $data['lang'], true);

            return $product;
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function columns(array $data): array
    {
        return [
            'name' => $data['name'], 'category_id' => $data['category_id'], 'tags' => $data['tags'],
            'photos' => $data['photos'] ?? null, 'thumbnail_img' => $data['thumbnail_img'] ?? null,
            'description' => $data['description'] ?? null, 'unit_price' => $data['unit_price'],
            'purchase_price' => $data['purchase_price'], 'discount' => $data['discount'], 'discount_type' => $data['discount_type'],
            'meta_title' => $data['meta_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null,
            'meta_img' => $data['meta_img'] ?? null, 'file_name' => $data['file'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function saveRelated(Product $product, array $data, string $lang, bool $replace): void
    {
        $this->syncStocks($product, [['variant' => '', 'price' => $data['unit_price'], 'qty' => 0, 'sku' => '', 'image' => null]]);
        $this->saveTaxes($product, $data, $replace);
        $this->saveTranslation($product, $lang, $data);
    }
}
