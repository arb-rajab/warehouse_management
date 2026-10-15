<?php

namespace App\Http\Resources\Store;

use App\Models\StoreUser;
use App\Models\Truck;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the trip lists: the distance in metres and the duration in seconds
 * as stored (the page formats them), the due date as a date and the start and
 * close times as ISO timestamps.
 *
 * @property-read int $id
 * @property-read string $code
 * @property-read string $status
 * @property-read CarbonInterface|null $due_date
 * @property-read CarbonInterface|null $started_at
 * @property-read CarbonInterface|null $closed_at
 * @property-read string|null $distance
 * @property-read string|null $duration
 * @property-read string|null $notes
 * @property-read StoreUser|null $driver
 * @property-read Truck|null $truck
 */
class TripResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'started_at' => $this->started_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'distance' => $this->distance === null ? null : (float) $this->distance,
            'duration' => $this->duration === null ? null : (int) $this->duration,
            'notes' => $this->notes,
            'driver' => $this->whenLoaded('driver', fn () => $this->driver === null ? null : ['id' => $this->driver->id, 'name' => $this->driver->name]),
            'truck' => $this->whenLoaded('truck', fn () => $this->truck === null ? null : ['id' => $this->truck->id, 'name' => $this->truck->name]),
        ];
    }
}
