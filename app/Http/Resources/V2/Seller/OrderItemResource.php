<?php

namespace App\Http\Resources\V2\Seller;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $description = $this->quantity;
        if($this->variation) {
            $description = $this->quantity. ' x '. $this->variation;
        }
        return [
            'name' => optional($this->product)->name,
            'description' => $description,
            'delivery_status' => $this->delivery_status,
            'price' => format_price($this->price),
        ];


    }
}
