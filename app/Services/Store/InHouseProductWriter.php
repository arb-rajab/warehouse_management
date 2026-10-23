<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\ProductStock;
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
 * #[Fillable] allows only name, ar_name, thumbnail_img and published, so outside strict
 * mode every other column was silently dropped (no category, price, unit,
 * slug or owner) and in local/testing the write throws. This writer computes
 * the same values those services compute and sets the columns explicitly; the
 * services stay unchanged for the seller API; the panel now uses
 * SellerProductWriter and shares the explicit computations in PhysicalProductWriter. Taxes and
 * flash deals still go through their services.
 *
 * Option A (shared-database.md): the store owns all columns except name,
 * ar_name and published on feed-origin rows. Product's assignment and builder
 * guards enforce that boundary here too; model inserts always have null
 * synced_at, including duplicates. The owner decision is recorded in §52.
 * The caller wraps each write in one transaction.
 */
class InHouseProductWriter extends PhysicalProductWriter
{
    public function __construct(
        ProductTaxService $taxes,
        private ProductFlashDealService $flashDeals,
    ) {
        parent::__construct($taxes);
    }

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
        app(ProductVariantCombinations::class)->assertLegacyStocksRetained($product, $variantNames);
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
