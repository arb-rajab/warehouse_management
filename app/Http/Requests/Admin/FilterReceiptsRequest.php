<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\FiltersByReceiptStatus;
use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use App\Http\Requests\Concerns\FiltersPerPage;
use App\Http\Requests\Concerns\SortsByDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin receipts list's query: optional `warehouse_ids[]`, `status`,
 * `sort_direction` (by creation time, newest first by default) and `per_page`.
 */
class FilterReceiptsRequest extends FormRequest
{
    use FiltersByReceiptStatus;
    use FiltersByWarehouseIds;
    use FiltersPerPage;
    use SortsByDirection;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->warehouseIdsFilterRules(),
            ...$this->receiptStatusFilterRules(),
            ...$this->sortDirectionRules(),
            ...$this->perPageRules(),
        ];
    }
}
