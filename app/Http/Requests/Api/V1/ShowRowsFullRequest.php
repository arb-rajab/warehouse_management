<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByProductStatus;
use App\Http\Requests\Concerns\FiltersByStaleAfterDays;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowRowsFullRequest extends FormRequest
{
    use FiltersByProductStatus;
    use FiltersByStaleAfterDays;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->staleAfterDaysFilterRules(),
            ...$this->productStatusFilterRules(),
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveProductStatusFilter();
    }
}
