<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Fills catalogue filter gaps without changing any pre-existing catalogue row. */
class StoreCatalogueFilterSeeder extends Seeder
{
    use RefusesProduction;

    public function run(): void
    {
        $this->refuseInProduction();
        if (! app()->environment('local', 'development', 'dev', 'staging', 'testing')) {
            throw new RuntimeException('Store filter fixtures require development, staging or testing.');
        }

        $data = app(StoreFilterSeedData::class);
        $admin = DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id') ?? $data->user('admin', ['user_type' => 'admin']);
        $seller = $data->user('seller', ['user_type' => 'seller']);
        $data->row('sellers', 'catalogue-seller', ['user_id' => $seller, 'verification_status' => 1]);
        $data->row('shops', 'catalogue-shop', ['user_id' => $seller, 'name' => 'Filter coverage shop', 'slug' => 'filter-coverage-shop', 'verification_status' => 1]);

        if (! DB::table('categories')->exists()) {
            $data->row('categories', 'catalogue-category', ['name' => 'Filter coverage', 'slug' => 'filter-coverage', 'parent_id' => 0, 'level' => 0]);
        }
        if (! DB::table('brands')->exists()) {
            $data->row('brands', 'catalogue-brand', ['name' => 'Filter coverage', 'slug' => 'filter-coverage']);
        }
        if (! DB::table('colors')->whereNotNull('code')->exists()) {
            $data->row('colors', 'catalogue-color', ['name' => 'Coverage blue', 'code' => '#2463EB']);
        }
        if (! DB::table('attribute_values')->exists()) {
            $attribute = $data->row('attributes', 'catalogue-attribute', ['name' => 'Coverage size']);
            $data->row('attribute_values', 'catalogue-attribute-value', ['attribute_id' => $attribute, 'value' => 'Coverage large']);
        }

        $category = (int) DB::table('categories')->orderBy('id')->value('id');
        $brand = (int) DB::table('brands')->orderBy('id')->value('id');
        $base = ['category_id' => $category, 'brand_id' => $brand, 'user_id' => $admin, 'added_by' => 'admin', 'digital' => 0, 'auction_product' => 0, 'wholesale_product' => 0];

        // Each switch is exact-match and the other switches retain their defaults.
        foreach (['admin' => $admin, 'seller' => $seller] as $ownerType => $owner) {
            foreach ([0, 1] as $published) {
                foreach ([0, 1] as $forSale) {
                    foreach ([0, 1] as $featured) {
                        foreach ($ownerType === 'seller' ? [0, 1] : [1] as $approved) {
                            $this->ensure($data, "state-$ownerType-$published-$forSale-$featured-$approved", [
                                ...$base, 'user_id' => $owner, 'added_by' => $ownerType, 'published' => $published,
                                'for_sale' => $forSale, 'featured' => $featured, 'approved' => $approved,
                            ]);
                        }
                    }
                }
            }
        }
        $this->ensure($data, 'deleted', [...$base, 'published' => 0, 'deleted_from_api' => 1]);
        $this->ensure($data, 'digital', [...$base, 'digital' => 1, 'published' => 1]);
        $public = [...$base, 'published' => 1, 'approved' => 1];
        foreach (['todays_deal', 'isNewProduct', 'isOfferProduct'] as $flag) {
            if (! DB::table('products')->where(['added_by' => 'admin', 'auction_product' => 0, 'digital' => 0, 'published' => 1, 'approved' => 1])->where($flag, 1)->exists()) {
                $this->ensure($data, 'public-collections', [...$public, 'featured' => 1, 'for_sale' => 1, 'todays_deal' => 1, 'isNewProduct' => 1, 'isOfferProduct' => 1]);
                break;
            }
        }
        $barcodeProduct = $this->ensure($data, 'barcode-public', $public);
        $data->row('product_barcodes', 'catalogue-barcode', ['product_id' => $barcodeProduct, 'barcode' => 'STORE-FILTER-COVERAGE']);

        // The category dropdown includes every category, including digital categories.
        foreach (DB::table('categories')->orderBy('id')->get(['id']) as $option) {
            foreach (['admin' => $admin, 'seller' => $seller] as $ownerType => $owner) {
                $this->ensure($data, "category-$option->id-$ownerType", [
                    ...$base, 'category_id' => $option->id, 'user_id' => $owner, 'added_by' => $ownerType,
                    'published' => 0, 'for_sale' => 0, 'featured' => 0,
                ]);
            }
            $this->ensure($data, "category-$option->id-public", [...$base, 'category_id' => $option->id, 'published' => 1, 'approved' => 1]);
        }
        foreach (DB::table('users')->where('user_type', 'seller')->orderBy('id')->get(['id']) as $option) {
            $this->ensure($data, "seller-$option->id", [...$base, 'user_id' => $option->id, 'added_by' => 'seller', 'published' => 0, 'for_sale' => 0, 'featured' => 0]);
        }
        foreach (DB::table('brands')->orderBy('id')->get(['id']) as $option) {
            $this->ensure($data, "brand-$option->id", [...$base, 'brand_id' => $option->id, 'published' => 1, 'approved' => 1]);
        }
        foreach (DB::table('colors')->whereNotNull('code')->orderBy('id')->get(['id', 'code']) as $option) {
            $query = DB::table('products')->where('published', 1)->where('approved', 1)->where('added_by', 'admin')->where('auction_product', 0)->where('colors', 'like', '%"'.$option->code.'"%');
            if (! $query->exists()) {
                $this->ensure($data, "color-$option->id", [...$base, 'published' => 1, 'approved' => 1, 'colors' => json_encode([$option->code], JSON_THROW_ON_ERROR)]);
            }
        }
        foreach (DB::table('attribute_values')->orderBy('id')->get(['id', 'attribute_id', 'value']) as $option) {
            $query = DB::table('products')->where('published', 1)->where('approved', 1)->where('added_by', 'admin')->where('auction_product', 0)->where('choice_options', 'like', '%"'.$option->value.'"%');
            if (! $query->exists()) {
                $this->ensure($data, "attribute-$option->id", [
                    ...$base, 'published' => 1, 'approved' => 1,
                    'attributes' => json_encode([(string) $option->attribute_id], JSON_THROW_ON_ERROR),
                    'choice_options' => json_encode([['attribute_id' => (string) $option->attribute_id, 'values' => [$option->value]]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
        $customer = $data->user('customer', ['user_type' => 'customer']);
        foreach (['category_id' => 'categories', 'brand_id' => 'brands'] as $column => $table) {
            foreach (DB::table($table)->orderBy('id')->get(['id']) as $option) {
                $references = ['category_id' => $category, 'brand_id' => $brand];
                $references[$column] = $option->id;
                foreach (['new', 'used'] as $condition) {
                    if (! DB::table('customer_products')->where($column, $option->id)->where('published', 1)->where('status', 1)->where('conditon', $condition)->exists()) {
                        $key = "classified-$table-$option->id-$condition";
                        $data->row('customer_products', $key, [
                            'name' => 'Filter coverage '.$condition, 'slug' => 'filter-coverage-'.$key,
                            'user_id' => $customer, 'added_by' => 'customer', ...$references,
                            'published' => 1, 'status' => 1, 'conditon' => $condition, 'unit_price' => 25,
                            'location' => 'Coverage location', 'unit' => 'box',
                        ]);
                    }
                }
            }
        }
        Cache::forget('verified_sellers_id');
    }

    /** @param array<string, mixed> $conditions */
    private function ensure(StoreFilterSeedData $data, string $key, array $conditions): int
    {
        $owned = $data->find('products', 'catalogue-'.$key);
        if ($owned === null) {
            $matches = $conditions;
            if (! str_starts_with($key, 'category-')) {
                unset($matches['category_id']);
            }
            if (! str_starts_with($key, 'brand-')) {
                unset($matches['brand_id']);
            }
            if (($matches['added_by'] ?? null) === 'admin' && $key !== 'barcode-public') {
                unset($matches['user_id']);
            }
            $existing = DB::table('products')->where($matches)->orderBy('id')->value('id');
            if ($existing !== null) {
                return (int) $existing;
            }
        }

        $id = (int) $data->row('products', 'catalogue-'.$key, [
            'name' => 'Filter coverage '.$key, 'ar_name' => 'منتج تجريبي', 'slug' => 'filter-coverage-'.$key,
            'serial' => 'filter-coverage-'.$key, 'mat_id' => 'filter-coverage-'.$key,
            'unit_price' => 25, 'current_stock' => 20, 'unit' => 'box', 'unit_equal' => 1,
            'discount' => 0, 'discount_type' => 'amount', 'tags' => 'filter-coverage',
            'colors' => '[]', 'choice_options' => '[]', 'attributes' => '[]',
            ...$conditions,
        ]);
        $data->row('product_stocks', 'catalogue-stock-'.$key, ['product_id' => $id, 'variant' => '', 'sku' => 'filter-coverage-'.$key, 'qty' => 20, 'price' => 25]);

        return $id;
    }
}
