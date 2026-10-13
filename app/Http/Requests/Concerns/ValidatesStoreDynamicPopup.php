<?php

namespace App\Http\Requests\Concerns;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait ValidatesStoreDynamicPopup
{
    use ValidatesStoreUploadReference;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function dynamicPopupRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'summary' => ['required', 'string', 'max:191'],
            'banner' => ['required', 'integer', $this->imageUploadRule()],
            'delay_seconds' => ['required', 'integer', 'min:0', 'max:100'],
            'btn_text' => ['required', 'string', 'max:191'],
            'offer_id' => ['required', 'integer', Rule::exists('offers', 'id')->where(fn (Builder $query) => $query->whereExists(fn (Builder $products) => $products
                ->selectRaw('1')->from('offer_products')->join('products', 'products.id', '=', 'offer_products.product_id')
                ->whereColumn('offer_products.offer_id', 'offers.id')->where('is_base_product', 1)
                ->whereNotNull('products.slug')->whereRaw("TRIM(products.slug) <> ''")))],
            'show_page' => ['bail', 'required', 'integer', 'min:0', function (string $attribute, mixed $value, \Closure $fail): void {
                if ((int) $value !== 0 && ! Category::query()->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            }],
        ];
    }
}
