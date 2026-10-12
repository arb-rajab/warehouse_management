<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreCategory;
use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    use ValidatesStoreCategory;
    use ValidatesStoreLanguage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->categoryRules($this->route('category')), ...$this->languageRules('required')];
    }
}
