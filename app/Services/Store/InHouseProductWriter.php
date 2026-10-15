<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductTax;
use App\Models\ProductTranslation;
use App\Models\ProductVariation;
use App\Models\StoreProductBarcode;
use App\Models\StoreUser;
use App\Services\ProductFlashDealService;
use App\Services\ProductTaxService;
use Illuminate\Support\Str;

/**
 * Writes a physical product from the validated create or edit form, as the
 * legacy ProductController::store() and update() meant to through
 * ProductService, ProductStockService, ProductTaxService,
 * ProductFlashDealService, the translation and the product type and barcode
 * loops.
 *
 * ProductService::store()/update() and ProductStockService::store() cannot
 * serve this path: they mass-assign the form's columns, and Product's
 * #[Fillable] allows only the four products:sync columns, so outside strict
 * mode every other column was silently dropped (no category, price, unit,
 * slug or owner) and in local/testing the write throws. This writer computes
 * the same values those services compute and sets the columns explicitly; the
 * services stay unchanged for the seller panel and the seller API. Taxes and
 * flash deals still go through their services.
 *
 * Creating or updating a product writes WMS-owned `products` beside
 * products:sync, the table's single writer (shared-database.md): an owner
 * decision kept as it was (.ai/rules/store-backoffice-migration.md §5, §§22–23).
 * The caller wraps each write in one transaction.
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
            ...$this->variantColumns($data, $variantNames, $colorCodes),
            ...$this->derivedColumns($data),
            'added_by' => 'admin',
            'user_id' => StoreUser::query()->where('user_type', 'admin')->whereNull('raizer_admin')->orderBy('id')->firstOrFail()->id,
            'approved' => 1,
            'shipping_cost' => ($data['shipping_type'] ?? null) === 'flat_rate' ? $data['flat_shipping_cost'] : 0,
            'slug' => $this->numberedSlug(Str::slug($data['name']), null),
            'published' => ($data['button'] ?? null) === 'unpublish' ? 0 : 1,
            'cash_on_delivery' => $cashPaymentShown ? (int) ($data['cash_on_delivery'] ?? false) : 1,
            'digital' => 0,
        ]);
        $product->save();

        $this->saveTaxes($product, $data, false);
        $this->saveFlashDeal($product, $data);
        foreach ($this->stockRows($data, $variantNames) as $row) {
            $stock = new ProductStock;
            $stock->forceFill(['product_id' => $product->id, ...$row])->save();
        }
        $this->saveTranslation($product, config('app.default_language'), $data);
        $this->saveTypesAndBarcodes($product, $data);

        return $product;
    }

    /**
     * Updates the product as the legacy update meant to: the base name, unit
     * and description change only in the default language, and the edited
     * language's translation is written; every other field is shared by the
     * languages and written whatever the language. Owner, approval and
     * publication are left as they are. A field whose business setting hides
     * it (the shipping section, cash on delivery, flash deals, taxes) keeps
     * its stored value instead of being reset.
     *
     * A stock row is kept, and updated, for each posted row whose variant it
     * already names (the first such row when names repeat); other posted rows
     * are added and the rows no posted row names are deleted. Taxes, product
     * types and store barcodes are replaced as before.
     *
     * @param  array<string, mixed>  $data  the validated edit form
     * @param  list<string>  $variantNames
     * @param  list<string>  $colorCodes
     */
    public function update(Product $product, array $data, array $variantNames, array $colorCodes, bool $cashPaymentShown, bool $productWiseShippingShown): Product
    {
        $columns = $this->postedColumns($data);
        if ($data['lang'] !== config('app.default_language')) {
            unset($columns['name'], $columns['unit'], $columns['description']);
        }
        $product->forceFill([
            ...$columns,
            ...$this->variantColumns($data, $variantNames, $colorCodes),
            ...$this->derivedColumns($data),
            'featured' => (int) (bool) ($data['featured'] ?? false),
            'todays_deal' => (int) (bool) ($data['todays_deal'] ?? false),
            'old_photo' => $data['old_photo'] ?? null,
        ]);
        if ($productWiseShippingShown) {
            $product->forceFill([
                'shipping_cost' => $data['shipping_type'] === 'flat_rate' ? $data['flat_shipping_cost'] : 0,
                'is_quantity_multiplied' => (int) (bool) ($data['is_quantity_multiplied'] ?? false),
            ]);
        }
        if ($cashPaymentShown) {
            $product->setAttribute('cash_on_delivery', (int) (bool) ($data['cash_on_delivery'] ?? false));
        }
        $product->slug = isset($data['slug']) && Str::slug($data['slug']) !== ''
            ? Str::slug($data['slug'])
            : $this->numberedSlug(Str::slug((string) $product->name), $product->id);
        $product->save();

        $this->saveTaxes($product, $data, true);
        $this->saveFlashDeal($product, $data);
        $this->syncStocks($product, $this->stockRows($data, $variantNames));
        $this->saveTranslation($product, $data['lang'], $data);
        $product->variations()->delete();
        $product->storeBarcodes()->delete();
        $this->saveTypesAndBarcodes($product, $data);

        return $product;
    }

    /**
     * The fields the form posts straight into `products` columns. A field the
     * form did not show (shipping, flash deal) is not among them, so on
     * create it keeps the column's default and on update its stored value.
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
     * What ProductService computes from the form on create and update alike:
     * the comma-joined tags, the discount period and the meta defaults (the
     * posted name and description, and the thumbnail).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function derivedColumns(array $data): array
    {
        $tags = array_values(array_unique(array_filter(array_map(trim(...), explode(',', (string) ($data['tags'] ?? ''))), fn (string $tag): bool => $tag !== '')));
        $dates = isset($data['date_range']) ? explode(' to ', $data['date_range']) : null;

        return [
            'tags' => implode(',', $tags),
            'discount_start_date' => $dates ? strtotime($dates[0]) : null,
            'discount_end_date' => $dates ? strtotime($dates[1]) : null,
            'meta_title' => $data['meta_title'] ?? $data['name'],
            'meta_description' => $data['meta_description'] ?? strip_tags((string) ($data['description'] ?? '')),
            'meta_img' => $data['meta_img'] ?? $data['thumbnail_img'] ?? null,
        ];
    }

    /**
     * The colour, choice and attribute JSON ProductService stores, and
     * whether the product has variant rows.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $variantNames
     * @param  list<string>  $colorCodes
     * @return array<string, mixed>
     */
    private function variantColumns(array $data, array $variantNames, array $colorCodes): array
    {
        $choices = array_map(fn (array $choice): array => ['attribute_id' => (string) $choice['attribute_id'], 'values' => array_values($choice['values'])], $data['choice_options'] ?? []);

        return [
            'colors' => json_encode($colorCodes),
            'choice_options' => json_encode($choices, JSON_UNESCAPED_UNICODE),
            'attributes' => json_encode(array_column($choices, 'attribute_id')),
            'variant_product' => $variantNames === [] ? 0 : 1,
        ];
    }

    /**
     * The slug, numbered after the other products whose slug begins with it,
     * as ProductService::store() numbered it.
     */
    private function numberedSlug(string $slug, ?int $ignore): string
    {
        $sameSlugCount = Product::query()->where('slug', 'like', $slug.'%')
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore))->count();

        return $slug.($sameSlugCount ? '-'.($sameSlugCount + 1) : '');
    }

    /**
     * One stock row per variant, at its own price, SKU, quantity and image;
     * or, with no variants, one row with an empty variant at the unit price
     * and the posted quantity and SKU, as ProductStockService writes them.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $variantNames
     * @return list<array{variant: string, price: mixed, sku: mixed, qty: mixed, image: mixed}>
     */
    private function stockRows(array $data, array $variantNames): array
    {
        return $variantNames === []
            ? [['variant' => '', 'price' => $data['unit_price'], 'sku' => $data['sku'] ?? null, 'qty' => $data['current_stock'], 'image' => null]]
            : array_values(array_map(fn (array $variant): array => [
                'variant' => (string) $variant['variant'],
                'price' => $variant['price'],
                'sku' => $variant['sku'] ?? null,
                'qty' => $variant['qty'],
                'image' => $variant['image'] ?? null,
            ], $data['variants']));
    }

    /**
     * Keeps the stored stock row of each posted variant, updated in place,
     * adds the new ones and deletes the rest.
     *
     * @param  list<array{variant: string, price: mixed, sku: mixed, qty: mixed, image: mixed}>  $rows
     */
    private function syncStocks(Product $product, array $rows): void
    {
        $stored = $product->stocks()->orderBy('id')->get();
        $kept = [];
        foreach ($rows as $row) {
            $stock = $stored->first(fn (ProductStock $stock): bool => (string) $stock->getAttribute('variant') === $row['variant'] && ! in_array($stock->getKey(), $kept, true))
                ?? new ProductStock;
            $stock->forceFill(['product_id' => $product->id, ...$row])->save();
            $kept[] = $stock->getKey();
        }
        $product->stocks()->whereNotIn('id', $kept)->delete();
    }

    /**
     * Posted taxes are stored through ProductTaxService; on update they first
     * replace every tax row of the product. With no taxes posted (no active
     * tax, or the tax section off), the product's rows stay, as before.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveTaxes(Product $product, array $data, bool $replace): void
    {
        if (empty($data['tax_id'])) {
            return;
        }
        if ($replace) {
            ProductTax::query()->where('product_id', $product->id)->delete();
        }
        $this->taxes->store(['tax_id' => $data['tax_id'], 'tax' => $data['tax'], 'tax_type' => $data['tax_type'], 'product_id' => $product->id]);
    }

    /**
     * A chosen flash deal gains the product, and the product takes the flash
     * discount and the deal's period; choosing none changes nothing, as
     * ProductFlashDealService does.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveFlashDeal(Product $product, array $data): void
    {
        if (array_key_exists('flash_deal_id', $data)) {
            $this->flashDeals->store([
                'flash_deal_id' => $data['flash_deal_id'] ?? null,
                'flash_discount' => $data['flash_discount'] ?? null,
                'flash_discount_type' => $data['flash_discount_type'] ?? null,
            ], $product);
        }
    }

    /** @param array<string, mixed> $data */
    private function saveTranslation(Product $product, string $lang, array $data): void
    {
        $translation = ProductTranslation::query()->firstOrNew(['product_id' => $product->id, 'lang' => $lang]);
        $translation->forceFill([
            'product_id' => $product->id,
            'lang' => $lang,
            'name' => $data['name'],
            'unit' => $data['unit'],
            'description' => $data['description'] ?? null,
        ])->save();
    }

    /**
     * The non-empty product types and store barcodes, as the legacy loops
     * created them.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveTypesAndBarcodes(Product $product, array $data): void
    {
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
    }
}
