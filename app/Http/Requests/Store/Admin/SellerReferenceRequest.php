<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SellerReferenceRequest extends FormRequest
{
    /**
     * Get the validation rules that should apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['id' => ['required', 'integer', Rule::exists('shops', 'id')]];
    }
}
