<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteSellersRequest extends FormRequest
{
    /**
     * Get the validation rules that should apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['id' => ['required', 'array', 'min:1', 'max:100'], 'id.*' => ['required', 'integer', 'distinct', Rule::exists('shops', 'id')]];
    }
}
