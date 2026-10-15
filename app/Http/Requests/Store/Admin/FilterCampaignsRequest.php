<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Foundation\Http\FormRequest;

class FilterCampaignsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    use FiltersPerPage;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->perPageRules() + ['search' => ['nullable', 'string', 'max:255']];
    }
}
