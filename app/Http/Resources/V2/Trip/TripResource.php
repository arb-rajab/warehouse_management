<?php

namespace App\Http\Resources\V2\Trip;

use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'driver' => [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'email' => $this->driver->email,
                'phone' => $this->driver->phone,
                'avatar_original' => uploaded_asset($this->driver->avatar_original),
            ],
            'truck' => [
                'id' => $this->truck->id,
                'name' => $this->truck->name,
                'license_plate' => $this->truck->license_plate,
                'max_pallets_number' => $this->truck->max_pallets_number,
                'photos' => get_images_path($this->truck->photos),
            ],

            'due_date' => $this->due_date,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
            'distance' => $this->distance,
            'checkpoints' => CheckpointResource::collection($this->checkpoints),
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}
