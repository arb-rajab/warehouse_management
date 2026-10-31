<?php

namespace App\Http\Resources\V2\Seller;

use App\Models\Product;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if($this->type == 'product_base') {

            $decoded_product = Arr::pluck(json_decode($this->details, true), 'product_id');
            $products = filter_products(Product::with(['category', 'stocks'])->whereIn('id', $decoded_product))->get();

        }

       // dd(json_encode(new ProductCollection($products)));
        return [
            'id' =>(int) $this->id,
            'type' => $this->type,
            'code' => $this->code,
            'details' => ($this->type == 'product_base') ?json_encode( new ProductCollection($products)) : $this->details,
            'discount' =>(float) $this->discount,
            'discount_type' => $this->discount_type,
            'start_date' => date('d/m/Y', $this->start_date),
            'end_date' => date('d/m/Y', $this->end_date),
        ];
    }
}
