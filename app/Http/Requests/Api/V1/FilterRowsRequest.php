<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/v1/rows` and `GET /api/v1/rows/frozen`: an optional
 * `warehouse_ids[]`; omitting it lists the rows of every warehouse.
 */
class FilterRowsRequest extends FormRequest
{
    use FiltersByWarehouseIds;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->warehouseIdsFilterRules();
    }
}
