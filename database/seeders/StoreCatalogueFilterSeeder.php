<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Database\Seeders\Support\StoreSeedExamples;
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
        $data->batch(function () use ($data): void {
            foreach ($data->records() as $record) {
                if ($record['table_name'] === 'products') {
                    $oldName = DB::table('products')->where('id', $record['record_id'])->value('name');
                    if (is_string($oldName) && str_starts_with($oldName, 'Filter coverage')) {
                        $data->row('products', $record['seed_key'], [
                            'name' => StoreSeedExamples::product($record['seed_key']), 'ar_name' => '',
                            'slug' => StoreSeedExamples::code('pantry', $record['seed_key']),
                            'serial' => StoreSeedExamples::code('SKU', $record['seed_key']),
                            'mat_id' => StoreSeedExamples::code('MAT', $record['seed_key']),
                            'tags' => 'pantry,groceries,mediterranean',
                        ]);
                    }
                }
            }
            $admin = DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id') ?? $data->user('admin', ['user_type' => 'admin']);
            $seller = $data->user('seller', ['user_type' => 'seller']);
            $data->row('sellers', 'catalogue-seller', ['user_id' => $seller, 'verification_status' => 1]);
            $data->row('shops', 'catalogue-shop', ['user_id' => $seller, 'name' => StoreSeedExamples::company('seller'), 'slug' => StoreSeedExamples::code('shop', 'seller'), 'verification_status' => 1]);

            if (! DB::table('categories')->exists() || $data->find('categories', 'catalogue-category') !== null) {
                $data->row('categories', 'catalogue-category', ['name' => 'Mediterranean Pantry', 'slug' => StoreSeedExamples::code('pantry', 'catalogue-reference'), 'parent_id' => 0, 'level' => 0]);
            }
            if (! DB::table('brands')->exists() || $data->find('brands', 'catalogue-brand') !== null) {
                $data->row('brands', 'catalogue-brand', ['name' => 'Mediterranean Pantry', 'slug' => StoreSeedExamples::code('pantry', 'catalogue-reference')]);
            }
            if (! DB::table('colors')->whereNotNull('code')->exists() || $data->find('colors', 'catalogue-color') !== null) {
                $data->row('colors', 'catalogue-color', ['name' => 'Royal Blue', 'code' => '#2463EB']);
            }
            if (! DB::table('attribute_values')->exists() || $data->find('attribute_values', 'catalogue-attribute-value') !== null) {
                $attribute = $data->row('attributes', 'catalogue-attribute', ['name' => 'Pack Size']);
                $data->row('attribute_values', 'catalogue-attribute-value', ['attribute_id' => $attribute, 'value' => 'Family Pack']);
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
                if (DB::table('products')->where(['added_by' => 'admin', 'auction_product' => 0, 'digital' => 0, 'published' => 1, 'approved' => 1])->where($flag, 1)->count() < StoreSeedExamples::STOREFRONT_ROWS) {
                    $this->ensure($data, 'public-collections', [...$public, 'featured' => 1, 'for_sale' => 1, 'todays_deal' => 1, 'isNewProduct' => 1, 'isOfferProduct' => 1]);
                    break;
                }
            }
            $barcodeProduct = $this->ensure($data, 'barcode-public', $public);
            $data->row('product_barcodes', 'catalogue-barcode', ['product_id' => $barcodeProduct, 'barcode' => '2000000000015']);

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
                if ($query->count() < StoreSeedExamples::STOREFRONT_ROWS || $data->find('products', 'catalogue-color-'.$option->id) !== null) {
                    $this->ensure($data, "color-$option->id", [...$base, 'published' => 1, 'approved' => 1, 'colors' => json_encode([$option->code], JSON_THROW_ON_ERROR)]);
                }
            }
            foreach (DB::table('attribute_values')->orderBy('id')->get(['id', 'attribute_id', 'value']) as $option) {
                $query = DB::table('products')->where('published', 1)->where('approved', 1)->where('added_by', 'admin')->where('auction_product', 0)->where('choice_options', 'like', '%"'.$option->value.'"%');
                if ($query->count() < StoreSeedExamples::STOREFRONT_ROWS || $data->find('products', 'catalogue-attribute-'.$option->id) !== null) {
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
                        $baseKey = "classified-$table-$option->id-$condition";
                        for ($index = 0; $index < StoreSeedExamples::ADMIN_ROWS; $index++) {
                            $key = $baseKey.($index === 0 ? '' : '-'.$index);
                            $data->row('customer_products', $key, [
                                'name' => StoreSeedExamples::product($key), 'slug' => StoreSeedExamples::code('pantry', $key),
                                'user_id' => $customer, 'added_by' => 'customer', ...$references,
                                'published' => 1, 'status' => 1, 'conditon' => $condition, 'unit_price' => StoreSeedExamples::price($key),
                                'location' => 'Amsterdam', 'unit' => 'box',
                            ]);
                        }
                    }
                }
            }
            Cache::forget('verified_sellers_id');
        });
    }

    /** @param array<string, mixed> $conditions */
    private function ensure(StoreFilterSeedData $data, string $key, array $conditions): int
    {
        $target = str_starts_with($key, 'state-') || in_array($key, ['deleted', 'digital'], true)
            || str_ends_with($key, '-admin') || str_ends_with($key, '-seller') || str_starts_with($key, 'seller-')
            ? StoreSeedExamples::ADMIN_ROWS : StoreSeedExamples::STOREFRONT_ROWS;
        if ($key === 'barcode-public') {
            $target = 1;
        }
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
        $colorsMatch = isset($matches['colors']) ? json_decode($matches['colors'], true, flags: JSON_THROW_ON_ERROR) : [];
        $choicesMatch = isset($matches['choice_options']) ? json_decode($matches['choice_options'], true, flags: JSON_THROW_ON_ERROR) : [];
        unset($matches['colors'], $matches['choice_options'], $matches['attributes']);
        $query = DB::table('products')->where($matches);
        foreach ($colorsMatch as $color) {
            $query->where('colors', 'like', '%"'.$color.'"%');
        }
        foreach ($choicesMatch as $choice) {
            foreach ($choice['values'] as $value) {
                $query->where('choice_options', 'like', '%"'.$value.'"%');
            }
        }
        $first = $query->orderBy('id')->value('id');
        $missing = max(0, $target - (clone $query)->count());
        $keys = collect($data->records())->where('table_name', 'products')->pluck('seed_key')
            ->filter(fn (string $seedKey): bool => $seedKey === 'catalogue-'.$key || str_starts_with($seedKey, 'catalogue-'.$key.'-copy-'));
        $ownedKeys = $keys->values()->all();
        // Repair missing owned rows as well as filling a shortfall in existing data.
        foreach ($ownedKeys as $ownedKey) {
            if ($data->find('products', $ownedKey) === null) {
                $missing = max(0, $missing - 1);
            }
        }
        for ($index = 0; $index < $missing; $index++) {
            $candidate = 'catalogue-'.$key;
            for ($copy = 1; in_array($candidate, $ownedKeys, true); $copy++) {
                $candidate = 'catalogue-'.$key.'-copy-'.$copy;
            }
            $ownedKeys[] = $candidate;
        }
        $colors = DB::table('colors')->whereNotNull('code')->pluck('code')->all();
        $choices = DB::table('attribute_values')->orderBy('id')->get()->groupBy('attribute_id')
            ->map(fn ($values, $attribute): array => ['attribute_id' => (string) $attribute, 'values' => $values->pluck('value')->all()])->values()->all();
        foreach ($ownedKeys as $ownedKey) {
            $token = substr($ownedKey, strlen('catalogue-'));
            $id = (int) $data->row('products', $ownedKey, [
                'name' => StoreSeedExamples::product($ownedKey), 'ar_name' => '',
                'slug' => StoreSeedExamples::code('pantry', $ownedKey),
                'serial' => StoreSeedExamples::code('SKU', $ownedKey), 'mat_id' => StoreSeedExamples::code('MAT', $ownedKey),
                'unit_price' => StoreSeedExamples::price($ownedKey), 'current_stock' => 20, 'unit' => 'box', 'unit_equal' => 1,
                'description' => 'Wholesale pantry essential supplied in sealed packs. Store in a cool, dry place and check the pack for ingredient and allergen information.',
                'discount' => 0, 'discount_type' => 'amount', 'tags' => 'pantry,groceries,mediterranean',
                'colors' => json_encode($colors, JSON_THROW_ON_ERROR),
                'choice_options' => json_encode($choices, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'attributes' => json_encode(array_column($choices, 'attribute_id'), JSON_THROW_ON_ERROR),
                ...$conditions,
            ]);
            $data->row('product_stocks', 'catalogue-stock-'.$token, ['product_id' => $id, 'variant' => '', 'sku' => StoreSeedExamples::code('SKU', $ownedKey), 'qty' => 20, 'price' => StoreSeedExamples::price($ownedKey)]);
            $first ??= $id;
        }

        return (int) $first;
    }
}
