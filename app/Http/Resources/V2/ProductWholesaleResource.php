<?php
namespace App\Http\Resources\V2;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ProductWholesaleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // return parent::toArray($request);
        return [
            // 'id' => $this->id,
            // 'product_stock_id' => $this->product_stock_id,
            'min_qty' => $this->min_qty,
            'max_qty' => $this->max_qty,
            'price' => single_price($this->price),
        ];
    }
}
