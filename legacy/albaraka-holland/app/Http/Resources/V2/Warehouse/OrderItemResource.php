<?php

namespace App\Http\Resources\V2\Warehouse;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ProductVariation;
use App\Models\Order;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $description = $this->quantity;
        if ($this->variation) {
            $description = $this->quantity . ' x ' . $this->variation;
        }


        $order = Order::findOrFail($this->order_id);


        $cartResults = [];
        // return $order->orderDetails;


        foreach ($order->orderDetails as $cart) {
            if (isset($cart->variation_qty) && $cart->variation_qty != 'null' && $cart->product_id == $this->product_id) {
                $jsonData = $cart->variation_qty;
                $cartResults = json_decode($jsonData, true);
            }
        }

        return [
            'name' => optional($this->product)->name,
            'product_id' => $this->product_id,
            'product_matId' => $this->product_matId,
            'product_serial' => $this->product_serial,
            'unit_equal'    => optional($this->product)->unit_equal,
            'unit_id'       => optional($this->product)->api_unitId,
            'description' => $description,
            'quantity' => $this->quantity,
            'variation' => $this->variation,
            'price' => $this->price,
            'original_price' => $this->original_price,
            'tax' => $this->tax,
            'item_notes' => $this->item_notes,
            'variation_quantity' => json_encode($cartResults),
        ];
    }
}
