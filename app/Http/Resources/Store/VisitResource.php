<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string|null $note
 * @property-read string|null $visit_date
 * @property-read bool $order_exists
 * @property-read StoreUser|null $rep
 * @property-read StoreUser|null $customer
 */
class VisitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'visit_date' => $this->visit_date,
            'has_order' => (bool) $this->order_exists,
            'representative' => $this->whenLoaded('rep', fn () => $this->rep === null ? null : ['id' => $this->rep->id, 'name' => $this->rep->name]),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : ['id' => $this->customer->id, 'name' => $this->customer->name]),
        ];
    }
}
