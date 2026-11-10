<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\FiltersByWarehouseIds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCellVerificationRoundRequest extends FormRequest
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
            // Omitting `row_ids` walks every row (of the `warehouse_ids`, when
            // given); naming rows walks only those. An *empty* array is rejected rather than read as
            // either: a client whose row list came out empty by accident would
            // otherwise silently claim every row and freeze pallet actions
            // warehouse-wide.
            'row_ids' => ['nullable', 'array', 'min:1'],
            'row_ids.*' => ['integer', 'distinct', 'exists:rows,id'],
            ...$this->warehouseIdsFilterRules(),
        ];
    }

    /**
     * The requested row ids as ints, or null when the caller named none and the
     * round should cover the whole warehouse. `exists:rows,id` has already
     * proved every id resolves.
     *
     * @return array<int, int>|null
     */
    public function rowIds(): ?array
    {
        if (! $this->filled('row_ids')) {
            return null;
        }

        return array_values(array_map('intval', $this->array('row_ids')));
    }
}
