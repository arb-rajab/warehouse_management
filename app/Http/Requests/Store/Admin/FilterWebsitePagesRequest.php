<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Foundation\Http\FormRequest;

class FilterWebsitePagesRequest extends FormRequest
{
    use FiltersPerPage;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->perPageRules()];
    }
}
