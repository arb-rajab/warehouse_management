<?php

namespace App\Http\Requests\Concerns;

use App\Models\Coupon;
use App\Models\StoreUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait ValidatesStoreCoupon
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function couponRules(?Coupon $coupon): array
    {
        $ownerId = StoreUser::query()->where('user_type', 'admin')->orderBy('id')->value('id');

        return [
            'type' => ['required', Rule::in(['product_base', 'cart_base'])],
            'code' => ['required', 'string', 'max:255', Rule::unique('coupons', 'code')->ignore($coupon)],
            'discount_type' => ['required', Rule::in(['amount', 'percent'])],
            'discount' => ['required', 'numeric', 'min:1', 'max:'.($this->input('discount_type') === 'percent' ? '100' : '999999999999999999.99')],
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'product_ids' => ['exclude_unless:type,product_base', 'required', 'array', 'min:1', 'max:1000'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where(fn (Builder $query) => filter_products($query->where('user_id', $ownerId)))],
            'min_buy' => ['exclude_unless:type,cart_base', 'required', 'numeric', 'min:1'],
            'max_discount' => ['exclude_unless:type,cart_base', 'required', 'numeric', 'min:1'],
        ];
    }
}
