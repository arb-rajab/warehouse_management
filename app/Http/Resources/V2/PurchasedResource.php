<?php

namespace App\Http\Resources\V2;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchasedResource extends JsonResource
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
            'id'=> $this->product->id,
            'name'=> $this->product->getTranslation('name'),
            'thumbnail_image' => uploaded_asset($this->product->thumbnail_img),
        ];
    }
}
