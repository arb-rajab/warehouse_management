<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** @mixin FormRequest */
trait ValidatesStoreDigitalProduct
{
    use ValidatesDiscountDateRange;
    use ValidatesStoreUploadReference;

    /** A `decimal(20,2)` amount of `products`, `product_stocks` or `product_taxes`. */
    private const array AMOUNT_RULES = ['numeric', 'min:0', 'max:999999999999999999.99', 'decimal:0,2'];

    /**
     * The `products` widths: the name is VARCHAR(200) (as is
     * `product_translations.name`), tags VARCHAR(500), photos VARCHAR(2000),
     * the amounts DECIMAL(20,2) and the discount type VARCHAR(10). The
     * description and meta texts are MEDIUMTEXT/LONGTEXT, wider than any
     * request the server accepts, so they have no length rule; the
     * description is cleaned by App\Services\HtmlSanitizer before validation.
     *
     * The category must be one the form offers: a digital root category or
     * any category below one. Photos, thumbnail and meta image are image
     * uploads, the file an upload of any type, as the legacy pickers offered
     * them; an unchanged stored reference round-trips without the owner check
     * (retainable*Rule()), any other must pass it. Taxes are the active
     * taxes the form lists, each with an amount and a type.
     *
     * @return array<string, mixed>
     */
    protected function digitalProductRules(?Product $product): array
    {
        $taxCount = is_array($this->input('tax_id')) ? count($this->input('tax_id')) : 0;

        return [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::in(array_column(Category::treeOptions(fn ($roots) => $roots->where('digital', 1)), 'id'))],
            'photos' => ['nullable', 'string', 'max:2000', $this->retainableImageUploadIdsRule($product?->getAttribute('photos'))],
            'thumbnail_img' => ['nullable', 'integer', $this->retainableImageUploadRule('thumbnail_img', $product?->thumbnail_img)],
            'tags' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', ...self::AMOUNT_RULES],
            'purchase_price' => ['required', ...self::AMOUNT_RULES],
            'discount' => ['required', ...self::AMOUNT_RULES],
            'discount_type' => ['required', 'string', Rule::in(['amount', 'percent'])],
            'date_range' => $this->discountDateRangeRules(),
            'tax_id' => ['nullable', 'array', 'list', 'max:100'],
            'tax_id.*' => ['required', 'integer', 'distinct', Rule::exists('taxes', 'id')->where('tax_status', 1)],
            'tax' => ['nullable', 'required_with:tax_id', 'array', 'list', 'size:'.$taxCount],
            'tax.*' => ['required', ...self::AMOUNT_RULES],
            'tax_type' => ['nullable', 'required_with:tax_id', 'array', 'list', 'size:'.$taxCount],
            'tax_type.*' => ['required', 'string', Rule::in(['amount', 'percent'])],
            'meta_title' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'meta_img' => ['nullable', 'integer', $this->retainableImageUploadRule('meta_img', $product?->getAttribute('meta_img'))],
            'file' => ['nullable', 'integer', $this->retainableUploadReferenceRule('file', $product?->getAttribute('file_name'), null)],
        ];
    }

    /** Cleans the description, so what is validated is what is stored. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => clean_html($this->input('description'))]);
        }
    }
}
