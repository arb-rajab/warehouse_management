<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReceiptStatus;
use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/receipts`: optional `warehouse_ids[]` and `status`; omitting
 * them lists every receipt.
 */
class FilterReceiptsRequest extends FormRequest
{
    use FiltersByWarehouseIds;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->warehouseIdsFilterRules(),
            /** Only drafts or only finalized receipts; omit for both. */
            'status' => ['nullable', Rule::enum(ReceiptStatus::class)],
        ];
    }
}
