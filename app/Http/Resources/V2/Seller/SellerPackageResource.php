<?php

namespace App\Http\Resources\V2\Seller;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerPackageResource extends JsonResource
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
            'id'                    =>(int) $this->id,
            'name'                  => $this->getTranslation('name'),
            'logo'                  => uploaded_asset($this->logo),
            'product_upload_limit'  =>(int) $this->product_upload_limit,
            'amount'                => ($this->amount > 0) ? single_price($this->amount) : __('storefront.common.free'),
            'price'                 => (float) $this->amount,
            'duration'              =>(int) $this->duration,
        ];
    }
}
