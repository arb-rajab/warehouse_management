<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesWebsitePage;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsitePageRequest extends FormRequest
{
    use ValidatesWebsitePage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->websitePageRules($this->route('page'));
    }
}
