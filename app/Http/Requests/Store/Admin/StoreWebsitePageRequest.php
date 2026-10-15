<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsitePage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebsitePageRequest extends FormRequest
{
    use ValidatesWebsitePage;

    protected function prepareForValidation(): void
    {
        $this->prepareWebsitePage();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->websitePageRules(null);
    }
}
