<?php

namespace App\Http\Requests\Store\Admin;

use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterUploadsRequest extends FormRequest
{
    use FiltersPerPage;

    /** Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->perPageRules(),
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'smallest', 'largest'])],
            'type' => ['nullable', Rule::in(['image', 'video', 'audio', 'archive', 'document'])],
        ];
    }
}
