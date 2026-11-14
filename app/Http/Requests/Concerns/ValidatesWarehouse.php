<?php

namespace App\Http\Requests\Concerns;

use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The warehouse form's fields, shared by StoreWarehouseRequest and
 * UpdateWarehouseRequest. Only `name` is required; `name` and `code` are
 * unique (the update ignores the warehouse being edited).
 */
trait ValidatesWarehouse
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function warehouseRules(?Warehouse $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('warehouses', 'name')->ignore($ignore?->id)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($ignore?->id)],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
