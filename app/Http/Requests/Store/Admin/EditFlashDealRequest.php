<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditFlashDealRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['lang' => ['nullable', 'string', Rule::in([config('app.default_language'), ...Language::query()->pluck('code')])]];
    }
}
