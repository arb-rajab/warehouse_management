<?php

namespace App\Services\Store;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductStock;
use App\Models\ProductVariation;
use App\Models\StoreUser;
use App\Rules\NewProductBarcode;
use App\Services\ProductFieldSourceService;
use App\Services\ProductFlashDealService;
use App\Services\ProductTaxService;
use Illuminate\Support\Facades\Validator;
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
 * Option A (shared-database.md): the store owns all columns except the
 * Otajer-owned synced ones (product_field_sources.php) on feed-origin rows. Product's assignment and builder
 * guards enforce that boundary here too; model inserts always have null
 * synced_at, including duplicates. The owner decision is recorded in §52.
 * Which synced fields are Otajer-owned is the admin's per-field choice
 * (ProductFieldSourceService); update() ignores a posted read-only one
 * (name, ar_name, unit, unit_price and description are on the form; the
 * others have no field there and only the guard protects them).
 * The caller wraps each write in one transaction, held under
 * NewProductBarcode::locked(): new barcodes are checked again right before
 * they are inserted, since `product_barcodes` has no unique index.
 */
class InHouseProductWriter extends PhysicalProductWriter
{
    public function __construct(
        ProductTaxService $taxes,
        private ProductFlashDealService $flashDeals,
        private ProductFieldSourceService $fieldSources,
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
            'ar_name' => (string) ($data['ar_name'] ?? ''),
            'added_by' => 'admin',
            'user_id' => StoreUser::query()->where('user_type', 'admin')->orderBy('id')->firstOrFail()->id,
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
        $this->saveTypes($product, $data);
        $this->insertBarcodes($product, null, $this->postedBarcodes($data));

        return $product;
    }

    /**
     * Updates the product as the legacy update meant to: the base name, unit
     * and description change only in the default language, and the edited
     * language's translation is written; every other field is shared by the
     * languages and written whatever the language. Owner, approval and
     * publication are left as they are. The Arabic name (`ar_name`) is shared
     * by the languages too, written when posted. A field whose business setting hides
     * it (the shipping section, cash on delivery, flash deals, taxes) keeps
     * its stored value instead of being reset.
     *
     * A stock row is kept, and updated, for each posted row whose variant it
     * already names (the first such row when names repeat); other posted rows
     * are added and the rows no posted row names are deleted. Taxes and
     * product types are replaced as before; barcodes are merged
     * ({@see self::mergeBarcodes()}).
     *
     * @param  array<string, mixed>  $data  the validated edit form
     * @param  list<string>  $variantNames
     * @param  list<string>  $colorCodes
     */
    public function update(Product $product, array $data, array $variantNames, array $colorCodes, bool $cashPaymentShown, bool $productWiseShippingShown): Product
    {
        app(ProductVariantCombinations::class)->assertLegacyStocksRetained($product, $variantNames);
        // Read on the locked row: an Otajer-owned field of a feed-origin
        // product keeps its synced value whatever was posted, and the
        // default-language translation mirrors that name.
        $readOnly = $this->fieldSources->readOnlyFieldsFor($product);
        // The name, unit and description are the default language's only;
        // other languages edit a translation the sync never writes. A stored
        // value may be null or blank (a feed row the sync never filled): the
        // translation mirrors it as a string, and the column is not written.
        $ignored = [];
        if ($data['lang'] === config('app.default_language')) {
            foreach (array_intersect(['name', 'unit', 'description'], $readOnly) as $field) {
                $data[$field] = $field === 'name' ? (string) $product->getAttribute($field) : $product->getAttribute($field);
                $ignored[] = $field;
            }
        }
        // The unit price is shared by the languages; the stock row of a
        // product with no variants is priced from it.
        if (in_array('unit_price', $readOnly, true)) {
            $data['unit_price'] = $product->getAttribute('unit_price') ?? 0;
            $ignored[] = 'unit_price';
        }
        // A read-only column keeps its stored value untouched, null included.
        $columns = array_diff_key($this->postedColumns($data), array_flip($ignored));
        if (array_key_exists('ar_name', $data) && ! in_array('ar_name', $readOnly, true)) {
            $columns['ar_name'] = (string) ($data['ar_name'] ?? '');
        }
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
        $this->saveTypes($product, $data);
        $this->mergeBarcodes($product, $data);

        return $product;
    }

    /**
     * Reconciles the product's barcodes with the form, comparing trimmed
     * values, so a store save never deletes a barcode it did not show:
     * - S is `product_barcodes_seen`, the values the form loaded; P the
     *   non-empty values posted;
     * - every row whose value is in S but not in P is deleted (the user
     *   removed it), duplicates of that value on this product included,
     *   except the own-id row, which no form deletes;
     * - each value of P the product does not hold is inserted, after the
     *   shared rule is checked again under the lock;
     * - every other row stays with its id: values still posted, and values
     *   added elsewhere (the WMS, the import, another tab) after the form
     *   loaded.
     * Without `product_barcodes_seen` (another client, an older page) S is
     * every value the product holds, which keeps the earlier replace
     * behaviour: the product ends up with exactly the posted values.
     *
     * @param  array<string, mixed>  $data
     */
    private function mergeBarcodes(Product $product, array $data): void
    {
        $posted = $this->postedBarcodes($data);
        $rows = $product->barcodes()->get(['id', 'product_id', 'barcode']);
        $held = $rows->map(fn (ProductBarcode $barcode): string => trim($barcode->barcode))->unique()->values()->all();
        $seen = array_key_exists('product_barcodes_seen', $data)
            ? $this->trimmedValues(is_array($data['product_barcodes_seen']) ? $data['product_barcodes_seen'] : [])
            : $held;

        // The own-id row (the import's Mat_ID, which the store app's barcode search needs) is never removed by a form.
        $removed = $rows->filter(fn (ProductBarcode $barcode): bool => in_array(trim($barcode->barcode), $seen, true) && ! in_array(trim($barcode->barcode), $posted, true) && ! $barcode->isOwnProductId());
        if ($removed->isNotEmpty()) {
            ProductBarcode::query()->whereKey($removed->modelKeys())->delete();
        }

        $this->insertBarcodes($product, $product, array_filter($posted, fn (string $value): bool => ! in_array($value, $held, true)));
    }

    /**
     * Inserts $values (by their form index), once the shared rule passes
     * again for every one: a value another product took since validation is
     * refused on its field, and the caller's transaction rolls back.
     *
     * @param  array<int, string>  $values
     */
    private function insertBarcodes(Product $product, ?Product $holder, array $values): void
    {
        if ($values === []) {
            return;
        }

        Validator::make(['product_barcodes' => $values], ['product_barcodes.*' => [new NewProductBarcode($holder)]])->validate();

        foreach ($values as $value) {
            ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => $value]);
        }
    }

    /**
     * The posted non-empty barcodes, trimmed, by their form index (the
     * request's `distinct` rule already refused repeats).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function postedBarcodes(array $data): array
    {
        $values = [];

        foreach (is_array($data['product_barcodes'] ?? null) ? $data['product_barcodes'] : [] as $index => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $values[(int) $index] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function trimmedValues(array $values): array
    {
        return array_values(array_filter(array_map(fn (mixed $value): string => trim((string) $value), $values), fn (string $value): bool => $value !== ''));
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
     * The non-empty product types, as the legacy loop created them.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveTypes(Product $product, array $data): void
    {
        foreach ($data['product_types'] ?? [] as $type) {
            if ($type !== null && $type !== '') {
                ProductVariation::query()->create(['product_id' => $product->id, 'name' => $type]);
            }
        }
    }
}
