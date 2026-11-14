<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/v1/products/{product}/pallets`: an optional `warehouse_ids[]`;
 * omitting it lists the product's pallets in every warehouse.
 */
class FilterProductPalletsRequest extends FormRequest
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
