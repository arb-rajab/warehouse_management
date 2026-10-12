<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesStoreBrand;
use App\Http\Requests\Concerns\ValidatesStoreLanguage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
{
    use ValidatesStoreBrand;
    use ValidatesStoreLanguage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->brandRules($this->route('brand')), ...$this->languageRules('required')];
    }
}
