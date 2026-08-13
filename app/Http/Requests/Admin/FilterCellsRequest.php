<?php

namespace App\Http\Requests\Admin;

use App\Enums\CellState;
use App\Http\Requests\Concerns\FiltersByRowAndExpiration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterCellsRequest extends FormRequest
{
    use FiltersByRowAndExpiration;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'state' => ['nullable', Rule::enum(CellState::class)],
            'stale' => ['nullable', 'boolean'],
            ...$this->rowAndExpirationFilterRules(),
            'sort_by' => ['nullable', 'in:expiration_date'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
