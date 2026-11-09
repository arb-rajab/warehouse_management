<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The id/name pair a row, cell or pallet payload carries for the warehouse it
 * belongs to, and the option shape of the admin warehouse filter's search —
 * deliberately narrower than WarehouseResource, which the warehouses admin
 * page lists.
 *
 * @property-read int $id
 * @property-read string $name
 */
class WarehouseSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
