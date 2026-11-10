<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByProductIds;
use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /api/v1/pallets/unplaced`: optional `warehouse_ids[]` and
 * `product_id[]`; omitting them lists every unplaced pallet.
 */
class FilterUnplacedPalletsRequest extends FormRequest
{
    use FiltersByProductIds;
    use FiltersByWarehouseIds;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->productIdsFilterRules(),
            ...$this->warehouseIdsFilterRules(),
        ];
    }
}
