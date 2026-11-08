<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use App\Http\Requests\Concerns\FiltersPerPage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterRowsRequest extends FormRequest
{
    use FiltersByWarehouseIds;
    use FiltersPerPage;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->warehouseIdsFilterRules(),
            ...$this->perPageRules(),
        ];
    }
}
