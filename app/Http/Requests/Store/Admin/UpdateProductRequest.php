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
 * round-trip whoever owns them, and the product's own store barcodes may be
 * posted again.
 *
 * `ar_name` (its rule is shared with the create form in
 * ValidatesStoreProduct), `unit`, `unit_price` and `description` are among the
 * products columns the Otajer sync can write. While a synced field is Otajer-owned on a
 * feed-origin product, the form shows it read-only and InHouseProductWriter
 * ignores what is posted for it (the stored value stays): see
 * ProductFieldSourceService and .ai/rules/product-field-sources.md. The
 * unit price is validated as stored, so the discount rule (below the unit
 * price) cannot be checked against a tampered value.
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
            ...$this->languageRules('required'),
            'slug' => ['nullable', 'string', $this->freeSlugRule($product)],
            'old_photo' => ['nullable', 'string', 'max:2000', $this->retainableImageUploadIdsRule($product->getAttribute('old_photo'))],
        ];
    }

    /** A read-only unit price is validated (and later written) as stored, whatever was posted. */
    protected function prepareForValidation(): void
    {
        $this->prepareProductFields();
        $product = $this->route('product');
        if ($product instanceof Product && in_array('unit_price', app(ProductFieldSourceService::class)->readOnlyFieldsFor($product), true)) {
            $this->merge(['unit_price' => ProductFormNumber::format($product->getAttribute('unit_price'))]);
        }
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return $this->productAfter();
    }
}
