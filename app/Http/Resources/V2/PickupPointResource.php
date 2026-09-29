<?php

namespace App\Http\Resources\V2;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickupPointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id"                	=> $this->id,
            "staff_id"             	=> $this->staff_id,
            "name"              	=> $this->name,
            "address"              	=> $this->address,
            "phone"       			=> $this->phone,
            "pick_up_status"        => $this->pick_up_status,
            "cash_on_pickup_status" => $this->cash_on_pickup_status,
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200,
        ];
    }
}
