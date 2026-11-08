<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use App\Http\Requests\Concerns\ValidatesStoreProduct;
use App\Models\Product;
use App\Services\ProductFieldSourceService;
use App\Support\ProductFormNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The physical product edit form, for a physical product only (404
 * otherwise, before any validation): the fields it shares with the create form
 * (ValidatesStoreProduct), the language being edited, the slug and the
 * Otajer image the edit form alone shows. Stored upload references
 * round-trip whoever owns them, and the product's own barcodes may be posted
 * again (ValidatesStoreProduct, with the `product_barcodes_seen` list the
 * barcode merge reads).
 *
 * `ar_name` (its rule is shared with the create form in
 * ValidatesStoreProduct), `unit`, `unit_price` and `description` are among the
 * products columns the Otajer sync can write. While a synced field is Otajer-owned on a
 * feed-origin product, the form shows it read-only and InHouseProductWriter
 * ignores what is posted for it (the stored value stays): see
 * ProductFieldSourceService and .ai/rules/product-field-sources.md. The
 * unit price is validated as stored, so the discount rule (below the unit
 * price) cannot be checked against a tampered value. A read-only field is
 * never validated as posted: the writer ignores it, and its stored value can
 * be null or blank (a feed row the sync never filled), which must not block
 * the save, so it only has to be absent or a string. A store-origin product
 * has no read-only field at all.
 */
class UpdateProductRequest extends FormRequest
{
    use ValidatesStoreLanguage;
    use ValidatesStoreProduct {
        prepareForValidation as private prepareProductFields;
    }

    /**
     * The slug is MEDIUMTEXT, so it has no length rule; once normalised it
     * must be free. The Otajer image holds comma-separated image ids in
     * VARCHAR(2000), as the legacy uploader stored them.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A digital product has its own form (digitalproducts.update).
        $product = $this->route('product');
        abort_unless($product instanceof Product && (int) $product->getAttribute('digital') === 0, 404);

        return [
            ...$this->productRules($product),
            ...$this->readOnlyFieldRules($product),
            ...$this->languageRules('required'),
            'slug' => ['nullable', 'string', $this->freeSlugRule($product)],
            'old_photo' => ['nullable', 'string', 'max:2000', $this->retainableImageUploadIdsRule($product->getAttribute('old_photo'))],
        ];
    }

    /**
     * The fields the form shows read-only and the writer ignores: every
     * Otajer-owned synced field of a feed-origin product (none on a
     * store-origin one), except that the name, unit and description are the
     * default language's only.
     *
     * @return list<string>
     */
    private function readOnlyFields(Product $product): array
    {
        $isDefaultLanguage = $this->input('lang') === config('app.default_language');

        return array_values(array_filter(
            app(ProductFieldSourceService::class)->readOnlyFieldsFor($product),
            fn (string $field): bool => $isDefaultLanguage || ! in_array($field, ['name', 'unit', 'description'], true),
        ));
    }

    /**
     * A read-only field's posted value is discarded, so its stored value
     * (possibly null) is not a reason to refuse the form: the shared rule,
     * which may require it, is replaced by one that accepts it missing.
     *
     * @return array<string, array<int, string>>
     */
    private function readOnlyFieldRules(Product $product): array
    {
        $rules = [];
        foreach (array_intersect($this->readOnlyFields($product), ['name', 'ar_name', 'unit', 'unit_price', 'description']) as $field) {
            $rules[$field] = ['nullable'];
        }

        return $rules;
    }

    /** A read-only unit price is validated (and later written) as stored, whatever was posted. */
    protected function prepareForValidation(): void
    {
        $this->prepareProductFields();
        $product = $this->route('product');
        if ($product instanceof Product && in_array('unit_price', $this->readOnlyFields($product), true)) {
            $this->merge(['unit_price' => ProductFormNumber::format($product->getAttribute('unit_price'))]);
        }
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return $this->productAfter();
    }
}
