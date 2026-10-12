<?php

namespace App\Http\Requests\Store\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTranslationsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $app = $this->route()->getActionMethod() === 'storeAppTranslations';

        return [
            'id' => ['required', 'integer', Rule::exists(Language::class, 'id')],
            'translations' => ['required', 'array', 'min:1', 'max:100'],
            'translations.*' => ['required', 'array:key,value'],
            'translations.*.key' => ['required', 'string', 'max:255', 'distinct', Rule::exists($app ? 'app_translations' : 'translations', 'lang_key')->where('lang', 'en')],
            'translations.*.value' => ['present', 'nullable', 'string', $app ? 'max:255' : 'max:65535'],
        ];
    }
}
