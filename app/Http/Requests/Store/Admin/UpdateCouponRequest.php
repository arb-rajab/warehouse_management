<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreCoupon;
use App\Models\Coupon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    use ValidatesStoreCoupon;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return $this->couponRules($coupon instanceof Coupon ? $coupon : null);
    }
}
