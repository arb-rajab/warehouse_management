<?php

namespace App\Http\Resources\V2\Trip;

use Illuminate\Http\Resources\Json\JsonResource;

class   CheckpointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if($this->isOrder()){
            $order_array = [
                'code' => $this->getOrder()?->code,
                'grand_total' => format_price($this->getOrder()?->grand_total),
                'order_details' => $this->getOrder()?->orderDetails?->map(function ($detail) {
                    return [
                        'id' => $detail->id,
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product_name,
                        'product_serial' => $detail->product_serial,
                        'variation' => $detail->variation,
                        'price' => $detail->price,
                        'quantity' => $detail->quantity,
                        'product_images' => get_images_path($detail->product->photos),
                    ];
                }),
            ];
        }

        return [
            'id' => $this->id,
            'customer_name' => $this->getCustomerForCheckpoint()?->name,
            'order' => $order_array ?? null,
            'type' => $this->type,
            'status' => $this->status,
            'arrived_at' => $this->arrived_at,
            'address' => [
            'country' => $this->country == [] ? null : $this->country,
            'city' => $this->city == [] ? null : $this->city,
            'state' => $this->state == [] ? null : $this->state,
            'address' => $this->address == [] ? null : $this->address,
        ],
            'shipping_address' => $this->getCustomerForCheckpoint()?->shipping_address,
            'longitudes' => $this->longitudes,
            'latitudes' => $this->latitudes,
            'phone' => $this->getCustomerForCheckpoint()?->phone,
            'email' => $this->getCustomerForCheckpoint()?->email,
            'tax_number' => $this->getCustomerForCheckpoint()?->tax_number,
            'notes' => $this->notes,
            'photos' => get_images_path($this->photos),
            'cmr_file' => uploaded_asset($this->cmr_file),
        ];
    }
}
