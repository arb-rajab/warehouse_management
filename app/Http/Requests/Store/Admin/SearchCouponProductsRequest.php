<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SearchCouponProductsRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255']];
    }
}
