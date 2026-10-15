<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use App\Http\Requests\Concerns\ValidatesStoreProduct;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The physical product edit form, for a physical product only (404
 * otherwise, before any validation): the fields it shares with the create form
 * (ValidatesStoreProduct), the language being edited, the slug and the
 * Otajer image the edit form alone shows. Stored upload references
 * round-trip whoever owns them, and the product's own store barcodes may be
 * posted again.
 */
class UpdateProductRequest extends FormRequest
{
    use ValidatesStoreLanguage;
    use ValidatesStoreProduct;

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

    /** @return array<int, callable> */
    public function after(): array
    {
        return $this->productAfter();
    }
}
