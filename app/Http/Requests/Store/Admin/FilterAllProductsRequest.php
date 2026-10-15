<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersStoreProductList;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The all-products list adds the legacy owner filter, the "deleted from
 * Otajer" switch and the five one-flag presets (`allProducts`, `allActive`,
 * …) that the Blade list linked to, under their legacy names.
 */
class FilterAllProductsRequest extends FormRequest
{
    use FiltersStoreProductList;

    /**
     * The legacy preset parameters, in the order the list gives them
     * precedence: `allProducts` drops every state filter, and otherwise the
     * first preset present applies on its own.
     *
     * @var list<string>
     */
    public const array PRESETS = ['allProducts', 'allDeleted', 'allActive', 'allForSale', 'allFeatured'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->productListFilterRules(),
            'deleted' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            ...array_fill_keys(self::PRESETS, ['nullable', 'boolean']),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeProductListFlags(['deleted', ...self::PRESETS]);
    }
}
