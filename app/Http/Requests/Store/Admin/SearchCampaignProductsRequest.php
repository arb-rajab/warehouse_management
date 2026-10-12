<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SearchCampaignProductsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255']];
    }
}
