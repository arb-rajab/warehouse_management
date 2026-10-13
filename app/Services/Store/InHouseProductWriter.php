<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductTranslation;
use App\Models\ProductVariation;
use App\Models\StoreProductBarcode;
use App\Models\StoreUser;
use App\Services\ProductFlashDealService;
use App\Services\ProductTaxService;
use Illuminate\Support\Str;

/**
 * Writes an in-house (admin) physical product from the validated create form,
 * as the legacy ProductController::store() meant to through ProductService,
 * ProductStockService, ProductTaxService, ProductFlashDealService, the
 * translation and the product type and barcode loops.
 *
 * ProductService::store() and ProductStockService::store() cannot serve this
 * path: they mass-assign the form's columns, and Product's #[Fillable] allows
 * only the four products:sync columns, so outside strict mode every other
 * column was silently dropped (no category, price, unit, slug or owner) and
 * in local/testing the write throws. This writer computes the same values
 * those services compute and sets the columns explicitly; the services stay
 * unchanged for the seller panel and the seller API. Taxes and flash deals
 * still go through their services.
 *
 * Creating a product writes WMS-owned `products` beside products:sync, the
 * table's single writer (shared-database.md): an owner decision kept as it
 * was (.ai/rules/store-backoffice-migration.md §5, §22). The caller wraps the
 * write in one transaction.
 */
class InHouseProductWriter
{
    public function __construct(
        private ProductTaxService $taxes,
        private ProductFlashDealService $flashDeals,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated create form
     * @param  list<string>  $variantNames  the variant rows the colours and choices generate
     * @param  list<string>  $colorCodes  the colours that count (none unless colours are on)
     */
    public function create(array $data, array $variantNames, array $colorCodes, bool $cashPaymentShown): Product
    {
        $product = new Product;
        $product->forceFill([
            ...$this->postedColumns($data),
            ...$this->derivedColumns($data, $colorCodes),
            'cash_on_delivery' => $cashPaymentShown ? (int) ($data['cash_on_delivery'] ?? false) : 1,
            'digital' => 0,
            'variant_product' => $variantNames === [] ? 0 : 1,
        ]);
        $product->save();

        if (! empty($data['tax_id'])) {
            $this->taxes->store(['tax_id' => $data['tax_id'], 'tax' => $data['tax'], 'tax_type' => $data['tax_type'], 'product_id' => $product->id]);
        }
        if (array_key_exists('flash_deal_id', $data)) {
            $this->flashDeals->store([
                'flash_deal_id' => $data['flash_deal_id'] ?? null,
                'flash_discount' => $data['flash_discount'] ?? null,
                'flash_discount_type' => $data['flash_discount_type'] ?? null,
            ], $product);
        }
        $this->saveStocks($product, $data, $variantNames);
        $translation = new ProductTranslation;
        $translation->forceFill([
            'product_id' => $product->id,
            'lang' => config('app.default_language'),
            'name' => $data['name'],
            'unit' => $data['unit'],
            'description' => $data['description'] ?? null,
        ])->save();
        foreach ($data['product_types'] ?? [] as $type) {
            if ($type !== null && $type !== '') {
                ProductVariation::query()->create(['product_id' => $product->id, 'name' => $type]);
            }
        }
        foreach ($data['product_barcodes'] ?? [] as $barcode) {
            if ($barcode !== null && $barcode !== '') {
                StoreProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => $barcode]);
            }
        }

        return $product;
    }

    /**
     * The fields the form posts straight into `products` columns. A field the
     * form did not show (shipping, flash deal) keeps the column's default.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function postedColumns(array $data): array
    {
        $columns = [
            'name', 'category_id', 'brand_id', 'unit', 'weight', 'max_qty', 'min_qty', 'photos', 'thumbnail_img',
            'video_provider', 'video_link', 'pdf', 'unit_price', 'discount', 'discount_type', 'current_stock',
            'external_link', 'external_link_btn', 'description', 'low_stock_quantity', 'stock_visibility_state',
            'shipping_type', 'est_shipping_days',
        ];
        $posted = array_intersect_key($data, array_flip($columns));
        foreach (['featured', 'todays_deal', 'is_quantity_multiplied'] as $switch) {
            if (array_key_exists($switch, $data)) {
                $posted[$switch] = (int) (bool) $data[$switch];
            }
        }

        return $posted;
    }

    /**
     * What ProductService::store() computes: the owner (the store's own admin
     * account, not the staff member), approval, the comma-joined tags, the
     * discount period, the meta defaults, the shipping cost, a unique slug,
     * the colour, choice and attribute JSON, and the publication from the
     * button pressed.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $colorCodes
     * @return array<string, mixed>
     */
    private function derivedColumns(array $data, array $colorCodes): array
    {
        $tags = array_values(array_unique(array_filter(array_map(trim(...), explode(',', (string) ($data['tags'] ?? ''))), fn (string $tag): bool => $tag !== '')));
        $dates = isset($data['date_range']) ? explode(' to ', $data['date_range']) : null;
        $slug = Str::slug($data['name']);
        $sameSlugCount = Product::query()->where('slug', 'like', $slug.'%')->count();
        $choices = array_map(fn (array $choice): array => ['attribute_id' => (string) $choice['attribute_id'], 'values' => array_values($choice['values'])], $data['choice_options'] ?? []);

        return [
            'added_by' => 'admin',
            'user_id' => StoreUser::query()->where('user_type', 'admin')->whereNull('raizer_admin')->orderBy('id')->firstOrFail()->id,
            'approved' => 1,
            'tags' => implode(',', $tags),
            'discount_start_date' => $dates ? strtotime($dates[0]) : null,
            'discount_end_date' => $dates ? strtotime($dates[1]) : null,
            'meta_title' => $data['meta_title'] ?? $data['name'],
            'meta_description' => $data['meta_description'] ?? strip_tags((string) ($data['description'] ?? '')),
            'meta_img' => $data['meta_img'] ?? $data['thumbnail_img'] ?? null,
            'shipping_cost' => ($data['shipping_type'] ?? null) === 'flat_rate' ? $data['flat_shipping_cost'] : 0,
            'slug' => $slug.($sameSlugCount ? '-'.($sameSlugCount + 1) : ''),
            'colors' => json_encode($colorCodes),
            'choice_options' => json_encode($choices, JSON_UNESCAPED_UNICODE),
            'attributes' => json_encode(array_column($choices, 'attribute_id')),
            'published' => ($data['button'] ?? null) === 'unpublish' ? 0 : 1,
        ];
    }

    /**
     * One stock row per variant, at its own price, SKU, quantity and image;
     * or, with no variants, one row with an empty variant at the unit price
     * and the posted quantity and SKU, as ProductStockService writes them.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $variantNames
     */
    private function saveStocks(Product $product, array $data, array $variantNames): void
    {
        $rows = $variantNames === []
            ? [['variant' => '', 'price' => $data['unit_price'], 'sku' => $data['sku'] ?? null, 'qty' => $data['current_stock'], 'image' => null]]
            : array_map(fn (array $variant): array => [
                'variant' => $variant['variant'],
                'price' => $variant['price'],
                'sku' => $variant['sku'] ?? null,
                'qty' => $variant['qty'],
                'image' => $variant['image'] ?? null,
            ], $data['variants']);
        foreach ($rows as $row) {
            $stock = new ProductStock;
            $stock->forceFill(['product_id' => $product->id, ...$row])->save();
        }
    }
}
