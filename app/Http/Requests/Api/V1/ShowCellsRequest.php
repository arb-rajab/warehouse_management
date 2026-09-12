<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByStaleAfterDays;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowCellsRequest extends FormRequest
{
    use FiltersByStaleAfterDays;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->staleAfterDaysFilterRules(),
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
