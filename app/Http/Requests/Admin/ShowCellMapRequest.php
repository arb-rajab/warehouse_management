<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByProductIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowCellMapRequest extends FormRequest
{
    use FiltersByProductIds;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'flat_number' => ['nullable', 'integer', 'min:1'],
            'state' => ['nullable', 'string', 'in:empty,full,opened'],
            'expires_within_days' => ['nullable', 'integer', 'min:0'],
            'expired' => ['nullable', 'boolean'],
            ...$this->productIdsFilterRules(),
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
