<?php

namespace App\Http\Requests\Store\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchOrderOptionsRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'kind' => ['required', Rule::in(['customers', 'representatives', 'products'])], 'page' => ['nullable', 'integer', 'min:1']];
    }
}
