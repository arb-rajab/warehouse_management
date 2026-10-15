<?php

namespace App\Http\Resources\Store;

use App\Models\Checkpoint;
use App\Models\Order;
use App\Models\StoreUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trip for the edit form: the due date as a date and the stored
 * checkpoints in their order. A stored checkpoint keeps its order or
 * customer, so each row carries what the form shows for it (the order's code
 * and customer, or the customer's name) instead of a choice list.
 *
 * @property-read int $id
 * @property-read string $code
 * @property-read string $status
 * @property-read int|null $driver_id
 * @property-read int|null $truck_id
 * @property-read CarbonInterface|null $due_date
 * @property-read string|null $notes
 * @property-read Collection<int, Checkpoint> $checkpoints
 */
class TripFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'driver_id' => $this->driver_id,
            'truck_id' => $this->truck_id,
            'due_date' => $this->due_date?->toDateString(),
            'notes' => $this->notes,
            'checkpoints' => $this->checkpoints->map(function (Checkpoint $checkpoint): array {
                $related = $checkpoint->getRelationValue('relationable');

                return [
                    'id' => $checkpoint->id,
                    'type' => $checkpoint->type,
                    'related_id' => (int) $checkpoint->relationable_id,
                    'notes' => $checkpoint->notes,
                    'order_code' => $related instanceof Order ? (string) $related->code : null,
                    'customer' => match (true) {
                        $related instanceof Order => $related->customer?->name,
                        $related instanceof StoreUser => $related->name,
                        default => null,
                    },
                ];
            })->values()->all(),
        ];
    }
}
