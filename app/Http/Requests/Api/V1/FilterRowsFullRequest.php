<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByProductStatus;
use App\Http\Requests\Concerns\FiltersByStaleAfterDays;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterRowsFullRequest extends FormRequest
{
    use FiltersByProductStatus;
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
            ...$this->productStatusFilterRules(),
        ];
    }
}
