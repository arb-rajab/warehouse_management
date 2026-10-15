<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLanguageStatusRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['id' => ['required', 'integer', Rule::exists(Language::class, 'id')], 'status' => ['required', 'boolean']];
    }
}
