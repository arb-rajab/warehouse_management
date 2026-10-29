<?php

namespace App\Http\Resources\V2\Seller;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerWithdrawResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $status = __('storefront.status.paid');
        if($this->status == 0) {
            $status = __('storefront.status.pending');
        }
        return [
            'id' => $this->id,
            'amount' => format_price($this->amount),
            'status' =>  $status,
            'created_at' => date('d-m-Y', strtotime($this->created_at)),
        ];
    }
}
