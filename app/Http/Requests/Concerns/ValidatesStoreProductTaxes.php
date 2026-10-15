<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** @mixin FormRequest */
trait ValidatesStoreProductTaxes
{
    /** A `decimal(20,2)` amount of `products`, `product_stocks` or `product_taxes`. */
    private const array AMOUNT_RULES = ['numeric', 'min:0', 'max:999999999999999999.99', 'decimal:0,2'];

    /**
     * The active taxes a product form lists (`tax_status = 1`), each posted as
     * `tax_id[]`, `tax[]` and `tax_type[]` of the same length, distinct taxes
     * only, as ProductTaxService stores them.
     *
     * @return array<string, mixed>
     */
    protected function productTaxRules(): array
    {
        $taxCount = is_array($this->input('tax_id')) ? count($this->input('tax_id')) : 0;

        return [
            'tax_id' => ['nullable', 'array', 'list', 'max:100'],
            'tax_id.*' => ['required', 'integer', 'distinct', Rule::exists('taxes', 'id')->where('tax_status', 1)],
            'tax' => ['nullable', 'required_with:tax_id', 'array', 'list', 'size:'.$taxCount],
            'tax.*' => ['required', ...self::AMOUNT_RULES],
            'tax_type' => ['nullable', 'required_with:tax_id', 'array', 'list', 'size:'.$taxCount],
            'tax_type.*' => ['required', 'string', Rule::in(['amount', 'percent'])],
        ];
    }
}
