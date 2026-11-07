<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\ValidatesSearchOptions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The query of a plain typeahead behind `StoreSearchSelect.vue` that adds no
 * parameter of its own: a search term and a page.
 */
class SearchOptionsRequest extends FormRequest
{
    use ValidatesSearchOptions;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return $this->searchOptionsRules();
    }
}
