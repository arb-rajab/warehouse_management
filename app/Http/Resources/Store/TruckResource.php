<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string|null $model
 * @property-read string|null $max_pallets_number
 * @property-read string|null $description
 * @property-read string|null $license_plate
 * @property-read string|null $photos
 * @property-read int|null $open_trip_id
 */
class TruckResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'model' => $this->model,
            'max_pallets_number' => $this->max_pallets_number,
            'description' => $this->description,
            'license_plate' => $this->license_plate,
            'photos' => array_values(array_map('intval', array_filter(explode(',', $this->photos ?? ''), fn (string $id) => ctype_digit($id)))),
            'open_trip_id' => $this->open_trip_id,
        ];
    }
}
