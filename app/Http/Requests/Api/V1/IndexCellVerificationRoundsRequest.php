<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\NormalizesBooleanFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexCellVerificationRoundsRequest extends FormRequest
{
    use NormalizesBooleanFilters;

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleanFilter('only_unfinished');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'only_unfinished' => ['nullable', 'boolean'],
        ];
    }
}
