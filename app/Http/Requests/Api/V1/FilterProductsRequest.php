<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByProductStatus;
use App\Http\Requests\Concerns\SearchesProductsByName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterProductsRequest extends FormRequest
{
    use FiltersByProductStatus;
    use SearchesProductsByName;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->productSearchRules(),
            ...$this->productStatusFilterRules(),
        ];
    }

    protected function passedValidation(): void
    {
        $this->resolveProductStatusFilter();
    }
}
