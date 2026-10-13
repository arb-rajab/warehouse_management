<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesStoreOrder
{
    /** @return array<string, array<mixed>> */
    protected function orderRules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('user_type', 'customer')->where('is_rep', 0)],
            'rep_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_rep', 1)],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'additional_info' => ['nullable', 'string', 'max:2000'],
            'products' => ['required', 'array', 'min:1', 'max:100'],
            'products.*.id' => ['nullable', 'integer', 'distinct'],
            'products.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('published', 1)->where('approved', 1)],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'products.*.price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'products.*.unit' => ['required', Rule::in(['PerBox', 'PerPiece'])],
            'products.*.stock_variant' => ['nullable', 'string', 'max:255'],
            'products.*.info' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
