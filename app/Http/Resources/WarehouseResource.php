<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A warehouse as the warehouses admin pages and the mobile warehouse list
 * show it. `rows_count` is present only when the caller counted the rows
 * (`withCount('rows')`); the delete button is offered only while it is 0.
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $code
 * @property-read string|null $address
 * @property-read int|null $rows_count
 */
class WarehouseResource extends JsonResource
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
            'code' => $this->code,
            'address' => $this->address,
            'rows_count' => $this->whenCounted('rows'),
        ];
    }
}
