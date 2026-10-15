<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryFeaturedRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'exists:categories,id'], 'status' => ['required', 'boolean']];
    }
}
