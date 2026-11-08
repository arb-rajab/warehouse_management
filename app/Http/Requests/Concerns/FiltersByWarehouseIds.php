<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation + accessor for requests that filter by a caller-chosen
 * set of warehouse ids (`warehouse_ids[]`) — the warehouse counterpart of
 * {@see FiltersByProductIds}. Omitting the parameter means every warehouse.
 */
trait FiltersByWarehouseIds
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function warehouseIdsFilterRules(): array
    {
        return [
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => ['integer', 'exists:warehouses,id'],
        ];
    }

    /**
     * The validated warehouse ids, or null when the caller didn't filter by warehouse.
     *
     * @return list<int>|null
     */
    public function warehouseIds(): ?array
    {
        return $this->filled('warehouse_ids') ? array_values(array_map('intval', $this->array('warehouse_ids'))) : null;
    }
}
